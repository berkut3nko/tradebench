#include "tradebench_core.h"
#include <iostream>
#include <memory>

using grpc::Server;
using grpc::ServerBuilder;

/**
 * @brief Starts the gRPC Server listening for PHP requests.
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
 * @brief Main entry point for the C++ application.
 */
int main() {
    std::cout << "Initializing TradeBench Core with Async Processing & Redis..." << std::endl;
    RunServer();
    return 0;
}