#include "tradebench_core.h"
#include <iostream>
#include <thread>
#include <hiredis/hiredis.h>

using grpc::ServerContext;
using grpc::Status;
using analyzer::AnalysisRequest;
using analyzer::AnalysisResponse;

/**
 * @brief Handles incoming gRPC requests for backtesting.
 * @param context gRPC server context.
 * @param request Contains the user ID, timeframe, strategy parameters, etc.
 * @param response Acknowledgment response sent back to PHP immediately.
 * @return gRPC Status code.
 */
Status AnalysisServiceImpl::StartAnalysis(ServerContext* context, const AnalysisRequest* request, AnalysisResponse* response) {
    std::string task_id = request->task_id();
    std::string strategy_payload = request->strategy_name();
    std::string timeframe = request->timeframe();
    
    std::cout << "[Core] RPC Task: " << task_id << " | Strategy: " << strategy_payload << " | Timeframe: " << timeframe << std::endl;

    const char* db_env = std::getenv("DB_CONNECTION");
    std::string conn_str = db_env ? db_env : "postgresql://user:pass@db:5432/analyzer_db";

    DataRepository repo(conn_str);
    bool is_db_ok = repo.checkConnection();

    if (is_db_ok && !task_id.empty()) {
        // Spawn a detached thread so the gRPC call returns immediately (Async Processing)
        std::thread([req = *request, task_id, strategy_payload, timeframe, conn_str]() {
            
            DataRepository thread_repo(conn_str);
            BacktestingEngine engine;

            // Fetch high-frequency ticks from PostgreSQL
            MarketData data = thread_repo.fetchPrices(req.currency_pair(), req.timeframe(), req.start_timestamp(), req.end_timestamp());
            
            std::string actual_strategy = strategy_payload;
            bool is_optimized = false; 
            
            // Trigger Evolutionary Genetic Algorithm if requested
            if (strategy_payload == "OPTIMIZE") {
                actual_strategy = engine.optimizeParameters(data);
                is_optimized = true; 
                std::cout << "[Core] Genetic Optimization finished. Found best strategy: " << actual_strategy << std::endl;
            }

            // Run the simulation using the specified or optimized strategy
            BacktestResult res = engine.runSimulation(data, actual_strategy);

            /* Prepare JSON Arrays for Frontend Chart Rendering */
            std::string equity_json = "[";
            for (size_t i = 0; i < res.equity_curve.size(); ++i) {
                equity_json += std::to_string(res.equity_curve[i]);
                if (i < res.equity_curve.size() - 1) equity_json += ",";
            }
            equity_json += "]";

            std::string buy_json = "[";
            for (size_t i = 0; i < res.buy_signals.size(); ++i) {
                buy_json += std::to_string(res.buy_signals[i]);
                if (i < res.buy_signals.size() - 1) buy_json += ",";
            }
            buy_json += "]";

            std::string sell_json = "[";
            for (size_t i = 0; i < res.sell_signals.size(); ++i) {
                sell_json += std::to_string(res.sell_signals[i]);
                if (i < res.sell_signals.size() - 1) sell_json += ",";
            }
            sell_json += "]";

            std::string ts_json = "[";
            for (size_t i = 0; i < res.timestamps.size(); ++i) {
                ts_json += std::to_string(res.timestamps[i]);
                if (i < res.timestamps.size() - 1) ts_json += ",";
            }
            ts_json += "]";

            /* Assemble Final Result Payload */
            std::string payload = "{\"task_id\": \"" + task_id + "\", "
                                + "\"status\": \"COMPLETED\", "
                                + "\"timeframe\": \"" + timeframe + "\", "
                                + "\"strategy\": \"" + actual_strategy + "\", "
                                + "\"is_optimized\": " + (is_optimized ? "true" : "false") + ", "
                                + "\"profit\": " + std::to_string(res.profit) + ", "
                                + "\"trades\": " + std::to_string(res.trades_count) + ", "
                                + "\"win_rate\": " + std::to_string(res.win_rate) + ", "
                                + "\"drawdown\": " + std::to_string(res.max_drawdown) + ", "
                                + "\"buy_signals\": " + buy_json + ", "
                                + "\"sell_signals\": " + sell_json + ", "
                                + "\"timestamps\": " + ts_json + ", "
                                + "\"equity\": " + equity_json + "}";

            // Save the complete JSON to the PostgreSQL database
            thread_repo.saveResult(task_id, payload);
            
            // Notify PHP backend/Frontend via Redis PubSub
            const char* redis_env = std::getenv("REDIS_HOST");
            std::string redis_host = redis_env ? redis_env : "redis";
            
            redisContext* rc = redisConnect(redis_host.c_str(), 6379);
            if (rc != nullptr && !rc->err) {
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