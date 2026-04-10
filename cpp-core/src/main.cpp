#include <iostream>
#include <memory>
#include <string>
#include <cstdlib>
#include <thread>
#include <chrono>
#include <vector>
#include <numeric>

#include <grpcpp/grpcpp.h>
#include <pqxx/pqxx>
#include <hiredis/hiredis.h>
#include "analysis.grpc.pb.h"

using grpc::Server;
using grpc::ServerBuilder;
using grpc::ServerContext;
using grpc::Status;
using analyzer::AnalysisService;
using analyzer::AnalysisRequest;
using analyzer::AnalysisResponse;

/**
 * @brief Structure to hold the result of a backtest simulation.
 */
struct BacktestResult {
    double profit;
    int trades_count;
    std::vector<double> equity_curve;
};

/**
 * @brief Handles all interactions with the PostgreSQL database.
 */
class DataRepository {
public:
    explicit DataRepository(const std::string& conn_str) : m_conn_str(conn_str) {}

    /**
     * @brief Checks if the database is reachable.
     * @return True if connected successfully.
     */
    bool checkConnection() const {
        try {
            pqxx::connection C(m_conn_str);
            return C.is_open();
        } catch (const std::exception& e) {
            std::cerr << "[Repo] DB Error: " << e.what() << std::endl;
            return false;
        }
    }

    /**
     * @brief Fetches historical close prices for a specific trading pair.
     * @param pair The currency pair to query (e.g., "BTCUSDT").
     * @return A vector of closing prices ordered chronologically.
     * @warning If the table is empty or the pair doesn't exist, it returns an empty vector.
     */
    std::vector<double> fetchPrices(const std::string& pair) {
        std::vector<double> prices;
        try {
            pqxx::connection C(m_conn_str);
            pqxx::work W(C);
            
            std::string query = "SELECT close_price FROM currency_data WHERE pair_name = " 
                              + W.quote(pair) + " ORDER BY tick_time ASC;";
            
            pqxx::result R = W.exec(query);
            for (auto row : R) {
                prices.push_back(row[0].as<double>());
            }
            std::cout << "[Repo] Fetched " << prices.size() << " ticks for " << pair << std::endl;
        } catch (const std::exception& e) {
            std::cerr << "[Repo] Fetch Error: " << e.what() << std::endl;
        }
        return prices;
    }

    /**
     * @brief Updates task status to COMPLETED in the database.
     * @param task_id Unique identifier of the task.
     * @param profit Calculated profit.
     */
    void saveResult(const std::string& task_id, double profit) {
        try {
            pqxx::connection C(m_conn_str);
            pqxx::work W(C);
            
            std::string sql = "UPDATE analysis_tasks SET status = 'COMPLETED' WHERE id = " + W.quote(task_id) + ";";
            W.exec(sql);
            W.commit();
            
            std::cout << "[Repo] DB updated: Task " << task_id << " marked as COMPLETED." << std::endl;
        } catch (const std::exception& e) {
            std::cerr << "[Repo] Save Error: " << e.what() << std::endl;
        }
    }

private:
    std::string m_conn_str;
};

/**
 * @brief Engine responsible for executing trading strategies.
 */
class BacktestingEngine {
public:
    /**
     * @brief Runs a simulated backtest using the Simple Moving Average (SMA) Crossover strategy.
     * @param prices Vector of historical closing prices.
     * @param strategy The strategy algorithm name (currently supports SMA_CROSS).
     * @return BacktestResult structure containing PnL, trade count, and equity progression over time.
     * @warning The function requires at least 21 data points to calculate the long SMA.
     */
    BacktestResult runSimulation(const std::vector<double>& prices, const std::string& strategy) {
        BacktestResult result;
        result.profit = 0.0;
        result.trades_count = 0;
        
        double initial_capital = 1000.0; 
        double current_capital = initial_capital;
        double crypto_amount = 0.0;
        bool in_position = false;

        const int short_window = 9;
        const int long_window = 21;

        if (prices.size() <= long_window) {
            std::cerr << "[Engine] Not enough data to run simulation." << std::endl;
            result.equity_curve.push_back(initial_capital);
            return result;
        }

        // Fill initial curve before long SMA is available
        for (int i = 0; i < long_window; ++i) {
            result.equity_curve.push_back(current_capital);
        }

        for (size_t i = long_window; i < prices.size(); ++i) {
            double short_sma = 0.0;
            double long_sma = 0.0;
            double prev_short_sma = 0.0;
            double prev_long_sma = 0.0;

            // Calculate current SMAs
            for(int j = 0; j < short_window; ++j) short_sma += prices[i - j];
            short_sma /= short_window;

            for(int j = 0; j < long_window; ++j) long_sma += prices[i - j];
            long_sma /= long_window;

            // Calculate previous SMAs for cross detection
            for(int j = 1; j <= short_window; ++j) prev_short_sma += prices[i - j];
            prev_short_sma /= short_window;

            for(int j = 1; j <= long_window; ++j) prev_long_sma += prices[i - j];
            prev_long_sma /= long_window;

            // Buy Signal: Short SMA crosses ABOVE Long SMA
            if (!in_position && prev_short_sma <= prev_long_sma && short_sma > long_sma) {
                crypto_amount = current_capital / prices[i];
                current_capital = 0.0;
                in_position = true;
                result.trades_count++;
            }
            // Sell Signal: Short SMA crosses BELOW Long SMA
            else if (in_position && prev_short_sma >= prev_long_sma && short_sma < long_sma) {
                current_capital = crypto_amount * prices[i];
                crypto_amount = 0.0;
                in_position = false;
                result.trades_count++;
            }

            // Record portfolio value
            double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
            result.equity_curve.push_back(portfolio_value);
        }

        // Finalize profit
        double final_value = in_position ? (crypto_amount * prices.back()) : current_capital;
        result.profit = final_value - initial_capital;
        
        std::cout << "[Engine] Simulation completed. Trades: " << result.trades_count 
                  << ", Profit: $" << result.profit << std::endl;
                  
        return result;
    }
};

/**
 * @brief Implementation of the gRPC Analysis Service.
 */
class AnalysisServiceImpl final : public AnalysisService::Service {
public:
    Status StartAnalysis(ServerContext* context, const AnalysisRequest* request, AnalysisResponse* response) override {
        std::string task_id = request->task_id();
        std::cout << "[Core] RPC received for Task: " << task_id << std::endl;

        const char* db_env = std::getenv("DB_CONNECTION");
        std::string conn_str = db_env ? db_env : "postgresql://user:pass@db:5432/analyzer_db";

        DataRepository repo(conn_str);
        bool is_db_ok = repo.checkConnection();

        if (is_db_ok && !task_id.empty()) {
            std::thread([req = *request, task_id, conn_str]() {
                
                DataRepository thread_repo(conn_str);
                BacktestingEngine engine;

                std::vector<double> prices = thread_repo.fetchPrices(req.currency_pair());
                BacktestResult res = engine.runSimulation(prices, req.strategy_name());

                thread_repo.saveResult(task_id, res.profit);
                
                const char* redis_env = std::getenv("REDIS_HOST");
                std::string redis_host = redis_env ? redis_env : "redis";
                
                redisContext* rc = redisConnect(redis_host.c_str(), 6379);
                if (rc != nullptr && !rc->err) {
                    
                    // Build JSON array for equity curve manually to avoid heavy dependencies
                    std::string equity_json = "[";
                    for (size_t i = 0; i < res.equity_curve.size(); ++i) {
                        equity_json += std::to_string(res.equity_curve[i]);
                        if (i < res.equity_curve.size() - 1) equity_json += ",";
                    }
                    equity_json += "]";

                    std::string payload = "{\"task_id\": \"" + task_id + "\", "
                                        + "\"status\": \"COMPLETED\", "
                                        + "\"profit\": " + std::to_string(res.profit) + ", "
                                        + "\"trades\": " + std::to_string(res.trades_count) + ", "
                                        + "\"equity\": " + equity_json + "}";

                    redisReply* reply = static_cast<redisReply*>(redisCommand(rc, "PUBLISH analysis_events %s", payload.c_str()));
                    if (reply != nullptr) {
                        std::cout << "[Redis] Published completion event for task " << task_id << std::endl;
                        freeReplyObject(reply);
                    }
                    redisFree(rc);
                } else {
                    std::cerr << "[Redis] Connection error" << std::endl;
                    if (rc != nullptr) redisFree(rc);
                }
            }).detach();
        }

        response->set_task_id(task_id);
        response->set_accepted(is_db_ok);
        response->set_message(is_db_ok ? "Task processing started in background." : "DB Connection FAILED.");
        
        return Status::OK;
    }
};

/**
 * @brief Configures and starts the gRPC server.
 */
void RunServer() {
    std::string server_address("0.0.0.0:50051");
    AnalysisServiceImpl service;

    ServerBuilder builder;
    builder.AddListeningPort(server_address, grpc::InsecureServerCredentials());
    builder.RegisterService(&service);
    
    std::unique_ptr<Server> server(builder.BuildAndStart());
    std::cout << "TradeBench Core listening on " << server_address << std::endl;
    
    server->Wait();
}

int main() {
    std::cout << "Initializing TradeBench Core with Async Processing & Redis..." << std::endl;
    RunServer();
    return 0;
}