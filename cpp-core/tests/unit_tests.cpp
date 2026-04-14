#include <gtest/gtest.h>
#include "tradebench_core.h"

/**
 * @brief Tests the SMA Crossover logic on a mock dataset.
 */
TEST(StrategyTest, SMACrossoverEmptyData) {
    BacktestingEngine engine;
    MarketData data; // Empty data
    
    BacktestResult result = engine.runSimulation(data, "SMA_CROSS:9:21");
    
    // Expect 0 trades and 0 profit on empty data
    EXPECT_EQ(result.trades_count, 0);
    EXPECT_DOUBLE_EQ(result.profit, 0.0);
}

/**
 * @brief Tests parameter parsing logic indirectly via default fallbacks.
 */
TEST(StrategyTest, InvalidPayloadFallback) {
    BacktestingEngine engine;
    MarketData data; 
    
    // Should fallback to default SMA 9/21 without crashing
    BacktestResult result = engine.runSimulation(data, "UNKNOWN_STRAT:INVALID:PARAMS");
    
    EXPECT_EQ(result.trades_count, 0);
}

int main(int argc, char **argv) {
    ::testing::InitGoogleTest(&argc, argv);
    return RUN_ALL_TESTS();
}