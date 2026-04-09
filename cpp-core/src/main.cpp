#include <iostream>
#include <memory>
#include <string>

#include <grpcpp/grpcpp.h>
#include "analysis.grpc.pb.h"

using grpc::Server;
using grpc::ServerBuilder;
using grpc::ServerContext;
using grpc::Status;
using analyzer::AnalysisService;
using analyzer::AnalysisRequest;
using analyzer::AnalysisResponse;

/**
 * @brief Implementation of the gRPC Analysis Service.
 */
class AnalysisServiceImpl final : public AnalysisService::Service {
    /**
     * @brief Handles the StartAnalysis RPC call from the PHP backend.
     * @param context gRPC server context.
     * @param request The analysis parameters sent by the client.
     * @param response The response to be sent back.
     * @return grpc::Status indicating success or failure.
     */
    Status StartAnalysis(ServerContext* context, const AnalysisRequest* request, AnalysisResponse* response) override {
        std::cout << "[Core] Received analysis request for pair: " << request->currency_pair() << std::endl;
        std::cout << "[Core] Strategy requested: " << request->strategy_name() << std::endl;
        
        /* Mocking task ID generation until DB is fully integrated */
        std::string mock_task_id = "task-uuid-1234-5678";
        
        response->set_task_id(mock_task_id);
        response->set_accepted(true);
        response->set_message("Task accepted by C++ core successfully.");
        
        return Status::OK;
    }
};

/**
 * @brief Configures and starts the gRPC server to listen for backend requests.
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

/**
 * @brief Main entry point for the TradeBench Analytical Core.
 * @return Execution status code.
 */
int main() {
    std::cout << "Initializing TradeBench Core..." << std::endl;
    RunServer();
    return 0;
}