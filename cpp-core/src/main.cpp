#include <iostream>
#include <memory>
#include <string>
#include <cstdlib>
#include <thread>
#include <chrono>
#include <vector>
#include <numeric>
#include <algorithm>

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
 * @brief Structure to hold the advanced result of a backtest simulation.
 */
struct BacktestResult {
    double profit;
    int trades_count;
    double win_rate;
    double max_drawdown;
    std::vector<double> equity_curve;
};

/**
 * @brief Handles all interactions with the PostgreSQL database.
 */
class DataRepository {
public:
    /**
     * @brief Constructor for DataRepository.
     * @param conn_str Connection string for PostgreSQL.
     */
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
     * @brief Fetches historical close prices for a specific trading pair within a timeframe.
     * @param pair The currency pair to query.
     * @param start_ts Unix timestamp for the start date.
     * @param end_ts Unix timestamp for the end date.
     * @return A vector of closing prices.
     */
    std::vector<double> fetchPrices(const std::string& pair, int64_t start_ts, int64_t end_ts) {
        std::vector<double> prices;
        try {
            pqxx::connection C(m_conn_str);
            pqxx::work W(C);
            
            std::string query = "SELECT close_price FROM currency_data WHERE pair_name = " 
                              + W.quote(pair) 
                              + " AND tick_time >= to_timestamp(" + std::to_string(start_ts) + ")"
                              + " AND tick_time <= to_timestamp(" + std::to_string(end_ts) + ")"
                              + " ORDER BY tick_time ASC;";
            
            pqxx::result R = W.exec(query);
            for (auto row : R) {
                prices.push_back(row[0].as<double>());
            }
            std::cout << "[Repo] Fetched " << prices.size() << " ticks for " << pair << " in given range." << std::endl;
        } catch (const std::exception& e) {
            std::cerr << "[Repo] Fetch Error: " << e.what() << std::endl;
        }
        return prices;
    }

    /**
     * @brief Updates task status and saves advanced metrics to the database.
     * @param task_id Unique identifier of the task.
     * @param strategy The name of the strategy used.
     * @param res The complete result structure.
     * @param equity_json JSON string of the equity curve.
     */
    void saveResult(const std::string& task_id, const std::string& strategy, const BacktestResult& res, const std::string& equity_json) {
        try {
            pqxx::connection C(m_conn_str);
            pqxx::work W(C);
            
            std::string sql_update = "UPDATE analysis_tasks SET status = 'COMPLETED' WHERE id = " + W.quote(task_id) + ";";
            W.exec(sql_update);

            /* Include strategy in the JSON result for history rendering */
            std::string result_data = "{\"strategy\": \"" + strategy + "\", "
                                    + "\"profit\": " + std::to_string(res.profit) 
                                    + ", \"trades\": " + std::to_string(res.trades_count) 
                                    + ", \"win_rate\": " + std::to_string(res.win_rate)
                                    + ", \"drawdown\": " + std::to_string(res.max_drawdown)
                                    + ", \"equity\": " + equity_json + "}";
                                    
            std::string sql_insert = "INSERT INTO analysis_results (task_id, result_data) VALUES (" + W.quote(task_id) + ", " + W.quote(result_data) + "::jsonb);";
            W.exec(sql_insert);

            W.commit();
            std::cout << "[Repo] DB updated: Task " << task_id << " results saved." << std::endl;
        } catch (const std::exception& e) {
            std::cerr << "[Repo] Save Error: " << e.what() << std::endl;
        }
    }

private:
    std::string m_conn_str;
};

/**
 * @brief Engine responsible for executing trading strategies and calculating metrics.
 */
class BacktestingEngine {
public:
    /**
     * @brief Runs SMA Crossover simulation with advanced metrics calculation.
     * @param prices Vector of historical closing prices.
     * @return BacktestResult with PnL, Drawdown, and WinRate.
     */
    BacktestResult runSimulation(const std::vector<double>& prices) {
        BacktestResult result;
        result.profit = 0.0;
        result.trades_count = 0;
        result.win_rate = 0.0;
        result.max_drawdown = 0.0;
        
        double initial_capital = 1000.0; 
        double current_capital = initial_capital;
        double crypto_amount = 0.0;
        
        double peak_capital = initial_capital;
        int winning_trades = 0;
        double entry_price = 0.0;
        bool in_position = false;

        const int short_window = 9;
        const int long_window = 21;

        if (prices.size() <= static_cast<size_t>(long_window)) {
            std::cerr << "[Engine] Not enough data to run simulation. Check date range." << std::endl;
            result.equity_curve.push_back(initial_capital);
            return result;
        }

        for (int i = 0; i < long_window; ++i) {
            result.equity_curve.push_back(current_capital);
        }

        for (size_t i = long_window; i < prices.size(); ++i) {
            double short_sma = 0.0, long_sma = 0.0;
            double prev_short_sma = 0.0, prev_long_sma = 0.0;

            for(int j = 0; j < short_window; ++j) short_sma += prices[i - j];
            short_sma /= short_window;

            for(int j = 0; j < long_window; ++j) long_sma += prices[i - j];
            long_sma /= long_window;

            for(int j = 1; j <= short_window; ++j) prev_short_sma += prices[i - j];
            prev_short_sma /= short_window;

            for(int j = 1; j <= long_window; ++j) prev_long_sma += prices[i - j];
            prev_long_sma /= long_window;

            /* Buy Signal */
            if (!in_position && prev_short_sma <= prev_long_sma && short_sma > long_sma) {
                crypto_amount = current_capital / prices[i];
                current_capital = 0.0;
                entry_price = prices[i];
                in_position = true;
            }
            /* Sell Signal */
            else if (in_position && prev_short_sma >= prev_long_sma && short_sma < long_sma) {
                current_capital = crypto_amount * prices[i];
                crypto_amount = 0.0;
                in_position = false;
                
                result.trades_count++;
                if (prices[i] > entry_price) {
                    winning_trades++;
                }
            }

            double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
            result.equity_curve.push_back(portfolio_value);
            
            if (portfolio_value > peak_capital) peak_capital = portfolio_value;
            
            double current_drawdown = (peak_capital - portfolio_value) / peak_capital * 100.0;
            if (current_drawdown > result.max_drawdown) result.max_drawdown = current_drawdown;
        }

        double final_value = in_position ? (crypto_amount * prices.back()) : current_capital;
        result.profit = final_value - initial_capital;
        
        if (result.trades_count > 0) {
            result.win_rate = (static_cast<double>(winning_trades) / result.trades_count) * 100.0;
        }
        
        std::cout << "[Engine] Simulation completed. Trades: " << result.trades_count 
                  << ", WR: " << result.win_rate << "%, MDD: " << result.max_drawdown << "%" << std::endl;
                  
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

                /* Fetch prices based on the provided timestamp range */
                std::vector<double> prices = thread_repo.fetchPrices(req.currency_pair(), req.start_timestamp(), req.end_timestamp());
                BacktestResult res = engine.runSimulation(prices);

                std::string equity_json = "[";
                for (size_t i = 0; i < res.equity_curve.size(); ++i) {
                    equity_json += std::to_string(res.equity_curve[i]);
                    if (i < res.equity_curve.size() - 1) equity_json += ",";
                }
                equity_json += "]";

                /* Save strategy name along with results */
                thread_repo.saveResult(task_id, req.strategy_name(), res, equity_json);
                
                const char* redis_env = std::getenv("REDIS_HOST");
                std::string redis_host = redis_env ? redis_env : "redis";
                
                redisContext* rc = redisConnect(redis_host.c_str(), 6379);
                if (rc != nullptr && !rc->err) {
                    
                    std::string payload = "{\"task_id\": \"" + task_id + "\", "
                                        + "\"status\": \"COMPLETED\", "
                                        + "\"strategy\": \"" + req.strategy_name() + "\", "
                                        + "\"profit\": " + std::to_string(res.profit) + ", "
                                        + "\"trades\": " + std::to_string(res.trades_count) + ", "
                                        + "\"win_rate\": " + std::to_string(res.win_rate) + ", "
                                        + "\"drawdown\": " + std::to_string(res.max_drawdown) + ", "
                                        + "\"equity\": " + equity_json + "}";

                    redisReply* reply = static_cast<redisReply*>(redisCommand(rc, "PUBLISH analysis_events %s", payload.c_str()));
                    if (reply != nullptr) freeReplyObject(reply);
                    redisFree(rc);
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