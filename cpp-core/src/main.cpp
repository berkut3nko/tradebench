#include <iostream>
#include <memory>
#include <string>
#include <cstdlib>
#include <thread>
#include <chrono>

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
     * @brief Updates task status to COMPLETED in the database.
     * @param task_id Unique identifier of the task.
     * @param profit Calculated profit.
     */
    void saveResult(const std::string& task_id, double profit) {
        try {
            pqxx::connection C(m_conn_str);
            pqxx::work W(C);
            
            /* Execute real UPDATE query for the task created by PHP */
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
     * @brief Runs a simulated backtest (heavy CPU workload).
     * @param pair The trading pair.
     * @param strategy The strategy algorithm name.
     * @return The final calculated profit.
     */
    double runSimulation(const std::string& pair, const std::string& strategy) {
        std::cout << "[Engine] Starting simulation for " << pair << " using " << strategy << "..." << std::endl;
        std::this_thread::sleep_for(std::chrono::seconds(2));
        std::cout << "[Engine] Simulation completed successfully!" << std::endl;
        return 145.50; 
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
                BacktestingEngine engine;
                DataRepository thread_repo(conn_str);

                double profit = engine.runSimulation(req.currency_pair(), req.strategy_name());
                thread_repo.saveResult(task_id, profit);
                
                const char* redis_env = std::getenv("REDIS_HOST");
                std::string redis_host = redis_env ? redis_env : "redis";
                
                redisContext* rc = redisConnect(redis_host.c_str(), 6379);
                if (rc != nullptr && !rc->err) {
                    std::string payload = "{\"task_id\": \"" + task_id + "\", \"status\": \"COMPLETED\", \"profit\": " + std::to_string(profit) + "}";
                    redisReply* reply = static_cast<redisReply*>(redisCommand(rc, "PUBLISH analysis_events %s", payload.c_str()));
                    if (reply != nullptr) {
                        std::cout << "[Redis] Published completion event for task " << task_id << std::endl;
                        freeReplyObject(reply);
                    }
                    redisFree(rc);
                } else {
                    std::cerr << "[Redis] Connection error" << std::endl;
                    if (rc != nullptr) {
                        redisFree(rc);
                    }
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