#include <iostream>
#include <memory>
#include <string>
#include <cstdlib>
#include <thread>
#include <chrono>
#include <vector>
#include <numeric>
#include <algorithm>
#include <sstream>

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
    explicit DataRepository(const std::string& conn_str) : m_conn_str(conn_str) {}

    bool checkConnection() const {
        try {
            pqxx::connection C(m_conn_str);
            return C.is_open();
        } catch (const std::exception& e) {
            std::cerr << "[Repo] DB Error: " << e.what() << std::endl;
            return false;
        }
    }

    std::vector<double> fetchPrices(const std::string& pair, const std::string& timeframe, int64_t start_ts, int64_t end_ts) {
        std::vector<double> prices;
        try {
            pqxx::connection C(m_conn_str);
            pqxx::work W(C);
            
            std::string query = "SELECT close_price FROM currency_data WHERE pair_name = " 
                              + W.quote(pair) 
                              + " AND timeframe = " + W.quote(timeframe)
                              + " AND tick_time >= to_timestamp(" + std::to_string(start_ts) + ")"
                              + " AND tick_time <= to_timestamp(" + std::to_string(end_ts) + ")"
                              + " ORDER BY tick_time ASC;";
            
            pqxx::result R = W.exec(query);
            for (auto row : R) {
                prices.push_back(row[0].as<double>());
            }
            std::cout << "[Repo] Fetched " << prices.size() << " ticks for " << pair << " (" << timeframe << ") in given range." << std::endl;
        } catch (const std::exception& e) {
            std::cerr << "[Repo] Fetch Error: " << e.what() << std::endl;
        }
        return prices;
    }

    void saveResult(const std::string& task_id, const std::string& strategy_payload, const BacktestResult& res, const std::string& equity_json) {
        try {
            pqxx::connection C(m_conn_str);
            pqxx::work W(C);
            
            std::string sql_update = "UPDATE analysis_tasks SET status = 'COMPLETED' WHERE id = " + W.quote(task_id) + ";";
            W.exec(sql_update);

            std::string result_data = "{\"strategy\": \"" + strategy_payload + "\", "
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
 * @brief Engine responsible for executing trading strategies.
 */
class BacktestingEngine {
public:
    /**
     * @brief Main simulation router. Parses payload and delegates to specific strategy logic.
     * @param prices Vector of historical closing prices.
     * @param strategy_payload String payload (e.g., "SMA_CROSS:9:21" or "RSI:14:30:70").
     * @return BacktestResult structure.
     */
    BacktestResult runSimulation(const std::vector<double>& prices, const std::string& strategy_payload) {
        std::vector<std::string> tokens = parsePayload(strategy_payload);
        std::string strategy_name = tokens.empty() ? "SMA_CROSS" : tokens[0];

        if (strategy_name == "SMA_CROSS" || strategy_name == "EMA_CROSS") {
            int fast = (tokens.size() > 1) ? std::stoi(tokens[1]) : 9;
            int slow = (tokens.size() > 2) ? std::stoi(tokens[2]) : 21;
            return runMovingAverageCross(prices, fast, slow, strategy_name == "EMA_CROSS");
        } 
        else if (strategy_name == "RSI") {
            int period = (tokens.size() > 1) ? std::stoi(tokens[1]) : 14;
            double overbought = (tokens.size() > 2) ? std::stod(tokens[2]) : 70.0;
            double oversold = (tokens.size() > 3) ? std::stod(tokens[3]) : 30.0;
            return runRSI(prices, period, overbought, oversold);
        }

        /* Fallback */
        return runMovingAverageCross(prices, 9, 21, false);
    }

private:
    /**
     * @brief Parses the colon-separated strategy payload.
     * @param payload Strategy payload string.
     * @return Vector of string tokens.
     */
    std::vector<std::string> parsePayload(const std::string& payload) {
        std::vector<std::string> tokens;
        std::stringstream ss(payload);
        std::string item;
        while (std::getline(ss, item, ':')) {
            tokens.push_back(item);
        }
        return tokens;
    }

    /**
     * @brief Executes SMA or EMA crossover strategy.
     */
    BacktestResult runMovingAverageCross(const std::vector<double>& prices, int fast_window, int slow_window, bool use_ema) {
        BacktestResult result = {0.0, 0, 0.0, 0.0, {}};
        double initial_capital = 1000.0; 
        double current_capital = initial_capital;
        double crypto_amount = 0.0;
        
        double peak_capital = initial_capital;
        int winning_trades = 0;
        double entry_price = 0.0;
        bool in_position = false;

        if (fast_window >= slow_window) fast_window = slow_window - 1;
        if (fast_window < 1) fast_window = 1;

        if (prices.size() <= static_cast<size_t>(slow_window)) {
            result.equity_curve.push_back(initial_capital);
            return result;
        }

        for (int i = 0; i < slow_window; ++i) result.equity_curve.push_back(current_capital);

        std::vector<double> fast_ma(prices.size(), 0.0);
        std::vector<double> slow_ma(prices.size(), 0.0);

        if (use_ema) {
            calculateEMA(prices, fast_window, fast_ma);
            calculateEMA(prices, slow_window, slow_ma);
        }

        for (size_t i = slow_window; i < prices.size(); ++i) {
            double current_fast = 0.0, current_slow = 0.0;
            double prev_fast = 0.0, prev_slow = 0.0;

            if (use_ema) {
                current_fast = fast_ma[i]; current_slow = slow_ma[i];
                prev_fast = fast_ma[i-1]; prev_slow = slow_ma[i-1];
            } else {
                for(int j = 0; j < fast_window; ++j) current_fast += prices[i - j];
                current_fast /= fast_window;
                for(int j = 0; j < slow_window; ++j) current_slow += prices[i - j];
                current_slow /= slow_window;
                for(int j = 1; j <= fast_window; ++j) prev_fast += prices[i - j];
                prev_fast /= fast_window;
                for(int j = 1; j <= slow_window; ++j) prev_slow += prices[i - j];
                prev_slow /= slow_window;
            }

            if (!in_position && prev_fast <= prev_slow && current_fast > current_slow) {
                crypto_amount = current_capital / prices[i];
                current_capital = 0.0;
                entry_price = prices[i];
                in_position = true;
            }
            else if (in_position && prev_fast >= prev_slow && current_fast < current_slow) {
                current_capital = crypto_amount * prices[i];
                crypto_amount = 0.0;
                in_position = false;
                result.trades_count++;
                if (prices[i] > entry_price) winning_trades++;
            }

            double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
            result.equity_curve.push_back(portfolio_value);
            if (portfolio_value > peak_capital) peak_capital = portfolio_value;
            double current_drawdown = (peak_capital - portfolio_value) / peak_capital * 100.0;
            if (current_drawdown > result.max_drawdown) result.max_drawdown = current_drawdown;
        }

        finalizeMetrics(result, prices.back(), initial_capital, current_capital, crypto_amount, in_position, winning_trades);
        return result;
    }

    /**
     * @brief Executes Relative Strength Index (RSI) mean-reversion strategy.
     */
    BacktestResult runRSI(const std::vector<double>& prices, int period, double overbought, double oversold) {
        BacktestResult result = {0.0, 0, 0.0, 0.0, {}};
        double initial_capital = 1000.0; 
        double current_capital = initial_capital;
        double crypto_amount = 0.0;
        
        double peak_capital = initial_capital;
        int winning_trades = 0;
        double entry_price = 0.0;
        bool in_position = false;

        if (prices.size() <= static_cast<size_t>(period)) {
            result.equity_curve.push_back(initial_capital);
            return result;
        }

        for (int i = 0; i < period; ++i) result.equity_curve.push_back(current_capital);

        std::vector<double> rsi_values;
        calculateRSI(prices, period, rsi_values);

        for (size_t i = period; i < prices.size(); ++i) {
            double current_rsi = rsi_values[i];
            double prev_rsi = rsi_values[i-1];

            /* Buy signal: RSI crosses above oversold level */
            if (!in_position && prev_rsi < oversold && current_rsi >= oversold) {
                crypto_amount = current_capital / prices[i];
                current_capital = 0.0;
                entry_price = prices[i];
                in_position = true;
            }
            /* Sell signal: RSI crosses below overbought level */
            else if (in_position && prev_rsi > overbought && current_rsi <= overbought) {
                current_capital = crypto_amount * prices[i];
                crypto_amount = 0.0;
                in_position = false;
                result.trades_count++;
                if (prices[i] > entry_price) winning_trades++;
            }

            double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
            result.equity_curve.push_back(portfolio_value);
            if (portfolio_value > peak_capital) peak_capital = portfolio_value;
            double current_drawdown = (peak_capital - portfolio_value) / peak_capital * 100.0;
            if (current_drawdown > result.max_drawdown) result.max_drawdown = current_drawdown;
        }

        finalizeMetrics(result, prices.back(), initial_capital, current_capital, crypto_amount, in_position, winning_trades);
        return result;
    }

    /**
     * @brief Helper to calculate Exponential Moving Average.
     */
    void calculateEMA(const std::vector<double>& prices, int period, std::vector<double>& ema) {
        double multiplier = 2.0 / (period + 1.0);
        double initial_sma = 0.0;
        for (int i = 0; i < period; ++i) initial_sma += prices[i];
        initial_sma /= period;
        
        ema[period - 1] = initial_sma;
        for (size_t i = period; i < prices.size(); ++i) {
            ema[i] = ((prices[i] - ema[i-1]) * multiplier) + ema[i-1];
        }
    }

    /**
     * @brief Helper to calculate Relative Strength Index.
     */
    void calculateRSI(const std::vector<double>& prices, int period, std::vector<double>& rsi_out) {
        rsi_out.resize(prices.size(), 0.0);
        double gain = 0.0, loss = 0.0;

        for (int i = 1; i <= period; ++i) {
            double change = prices[i] - prices[i-1];
            if (change > 0) gain += change;
            else loss -= change;
        }
        gain /= period;
        loss /= period;
        
        rsi_out[period] = loss == 0 ? 100.0 : 100.0 - (100.0 / (1.0 + (gain / loss)));

        for (size_t i = period + 1; i < prices.size(); ++i) {
            double change = prices[i] - prices[i-1];
            double current_gain = change > 0 ? change : 0.0;
            double current_loss = change < 0 ? -change : 0.0;

            gain = ((gain * (period - 1)) + current_gain) / period;
            loss = ((loss * (period - 1)) + current_loss) / period;

            if (loss == 0) rsi_out[i] = 100.0;
            else rsi_out[i] = 100.0 - (100.0 / (1.0 + (gain / loss)));
        }
    }

    /**
     * @brief Finalizes PnL and Win Rate calculation.
     */
    void finalizeMetrics(BacktestResult& res, double last_price, double initial, double current, double amount, bool in_pos, int wins) {
        double final_value = in_pos ? (amount * last_price) : current;
        res.profit = final_value - initial;
        if (res.trades_count > 0) {
            res.win_rate = (static_cast<double>(wins) / res.trades_count) * 100.0;
        }
    }
};

/**
 * @brief Implementation of the gRPC Analysis Service.
 */
class AnalysisServiceImpl final : public AnalysisService::Service {
public:
    Status StartAnalysis(ServerContext* context, const AnalysisRequest* request, AnalysisResponse* response) override {
        std::string task_id = request->task_id();
        std::string strategy_payload = request->strategy_name();
        std::string timeframe = request->timeframe();
        
        std::cout << "[Core] RPC Task: " << task_id << " | Strategy: " << strategy_payload << " | Timeframe: " << timeframe << std::endl;

        const char* db_env = std::getenv("DB_CONNECTION");
        std::string conn_str = db_env ? db_env : "postgresql://user:pass@db:5432/analyzer_db";

        DataRepository repo(conn_str);
        bool is_db_ok = repo.checkConnection();

        if (is_db_ok && !task_id.empty()) {
            std::thread([req = *request, task_id, strategy_payload, conn_str]() {
                
                DataRepository thread_repo(conn_str);
                BacktestingEngine engine;

                std::vector<double> prices = thread_repo.fetchPrices(req.currency_pair(), req.timeframe(), req.start_timestamp(), req.end_timestamp());
                BacktestResult res = engine.runSimulation(prices, strategy_payload);

                std::string equity_json = "[";
                for (size_t i = 0; i < res.equity_curve.size(); ++i) {
                    equity_json += std::to_string(res.equity_curve[i]);
                    if (i < res.equity_curve.size() - 1) equity_json += ",";
                }
                equity_json += "]";

                thread_repo.saveResult(task_id, strategy_payload, res, equity_json);
                
                const char* redis_env = std::getenv("REDIS_HOST");
                std::string redis_host = redis_env ? redis_env : "redis";
                
                redisContext* rc = redisConnect(redis_host.c_str(), 6379);
                if (rc != nullptr && !rc->err) {
                    
                    std::string payload = "{\"task_id\": \"" + task_id + "\", "
                                        + "\"status\": \"COMPLETED\", "
                                        + "\"strategy\": \"" + strategy_payload + "\", "
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