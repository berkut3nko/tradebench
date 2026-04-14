#pragma once

#include <string>
#include <vector>
#include <pqxx/pqxx>
#include <grpcpp/grpcpp.h>
#include "analysis.grpc.pb.h"

/**
 * @brief Structure to hold market data including timestamps.
 */
struct MarketData {
    std::vector<long long> timestamps;
    std::vector<double> prices;
};

/**
 * @brief Structure to hold the advanced result of a backtest simulation.
 */
struct BacktestResult {
    double profit;
    int trades_count;
    double win_rate;
    double max_drawdown;
    std::vector<double> equity_curve;
    std::vector<int> buy_signals;
    std::vector<int> sell_signals;
    std::vector<long long> timestamps; 
};

/**
 * @brief Handles all interactions with the PostgreSQL database.
 */
class DataRepository {
public:
    explicit DataRepository(const std::string& conn_str);
    bool checkConnection() const;
    MarketData fetchPrices(const std::string& pair, const std::string& timeframe, int64_t start_ts, int64_t end_ts);
    void saveResult(const std::string& task_id, const std::string& result_data);
private:
    std::string m_conn_str;
};

/**
 * @brief Engine responsible for executing trading strategies.
 */
class BacktestingEngine {
public:
    std::string optimizeParameters(const MarketData& market_data);
    BacktestResult runSimulation(const MarketData& market_data, const std::string& strategy_payload);

private:
    std::vector<std::string> parsePayload(const std::string& payload);
    BacktestResult runMovingAverageCross(const MarketData& market_data, int fast_window, int slow_window, bool use_ema);
    BacktestResult runRSI(const MarketData& market_data, int period, double overbought, double oversold);
    BacktestResult runMACD(const MarketData& market_data, int fast_period, int slow_period, int signal_period);
    BacktestResult runBollingerBands(const MarketData& market_data, int period, double std_dev_multiplier);
    
    void calculateEMA(const std::vector<double>& source, int period, int start_idx, std::vector<double>& ema_out);
    void calculateRSI(const std::vector<double>& prices, int period, std::vector<double>& rsi_out);
    void finalizeMetrics(BacktestResult& res, double last_price, double initial, double current, double amount, bool in_pos, int wins);
};

/**
 * @brief Implementation of the gRPC Analysis Service.
 */
class AnalysisServiceImpl final : public analyzer::AnalysisService::Service {
public:
    grpc::Status StartAnalysis(grpc::ServerContext* context, const analyzer::AnalysisRequest* request, analyzer::AnalysisResponse* response) override;
};