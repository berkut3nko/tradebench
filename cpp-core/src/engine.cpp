#include "tradebench_core.h"
#include <cmath>
#include <random>
#include <sstream>
#include <algorithm>

/**
 * @brief Parses the strategy payload string into separate tokens.
 * @param payload The strategy string (e.g., "MACD:12:26:9").
 * @return A vector of parsed string tokens.
 */
std::vector<std::string> BacktestingEngine::parsePayload(const std::string& payload) {
    std::vector<std::string> tokens;
    std::stringstream ss(payload);
    std::string item;
    while (std::getline(ss, item, ':')) {
        tokens.push_back(item);
    }
    return tokens;
}

/**
 * @brief Calculates final profit and win rate metrics for the backtest.
 * @param res The result structure to update.
 * @param last_price The final price of the asset.
 * @param initial Initial capital.
 * @param current Current fiat capital.
 * @param amount Current crypto holdings.
 * @param in_pos Whether a position is currently open.
 * @param wins Total number of winning trades.
 */
void BacktestingEngine::finalizeMetrics(BacktestResult& res, double last_price, double initial, double current, double amount, bool in_pos, int wins) {
    double final_value = in_pos ? (amount * last_price) : current;
    res.profit = final_value - initial;
    if (res.trades_count > 0) {
        res.win_rate = (static_cast<double>(wins) / res.trades_count) * 100.0;
    }
}

/**
 * @brief Calculates the Exponential Moving Average (EMA).
 * @param source Input data vector.
 * @param period The EMA window period.
 * @param start_idx The index to start calculating from.
 * @param ema_out The output vector to store EMA values.
 */
void BacktestingEngine::calculateEMA(const std::vector<double>& source, int period, int start_idx, std::vector<double>& ema_out) {
    if (source.size() < static_cast<size_t>(start_idx + period)) return;
    double multiplier = 2.0 / (period + 1.0);
    double sum = 0.0;
    for (int i = 0; i < period; ++i) {
        sum += source[start_idx + i];
    }
    ema_out[start_idx + period - 1] = sum / period;
    for (size_t i = start_idx + period; i < source.size(); ++i) {
        ema_out[i] = ((source[i] - ema_out[i-1]) * multiplier) + ema_out[i-1];
    }
}

/**
 * @brief Calculates the Relative Strength Index (RSI).
 * @param prices Input price vector.
 * @param period The RSI calculation period.
 * @param rsi_out The output vector to store RSI values.
 */
void BacktestingEngine::calculateRSI(const std::vector<double>& prices, int period, std::vector<double>& rsi_out) {
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
 * @brief Routes the simulation request to the appropriate mathematical strategy.
 * @param market_data The dataset to test against.
 * @param strategy_payload The formatted string containing strategy name and parameters.
 * @return A complete BacktestResult object.
 */
BacktestResult BacktestingEngine::runSimulation(const MarketData& market_data, const std::string& strategy_payload) {
    std::vector<std::string> tokens = parsePayload(strategy_payload);
    std::string strategy_name = tokens.empty() ? "SMA_CROSS" : tokens[0];

    if (strategy_name == "SMA_CROSS" || strategy_name == "EMA_CROSS") {
        int fast = (tokens.size() > 1) ? std::stoi(tokens[1]) : 9;
        int slow = (tokens.size() > 2) ? std::stoi(tokens[2]) : 21;
        return runMovingAverageCross(market_data, fast, slow, strategy_name == "EMA_CROSS");
    } 
    else if (strategy_name == "RSI") {
        int period = (tokens.size() > 1) ? std::stoi(tokens[1]) : 14;
        double overbought = (tokens.size() > 2) ? std::stod(tokens[2]) : 70.0;
        double oversold = (tokens.size() > 3) ? std::stod(tokens[3]) : 30.0;
        return runRSI(market_data, period, overbought, oversold);
    }
    else if (strategy_name == "MACD") {
        int fast = (tokens.size() > 1) ? std::stoi(tokens[1]) : 12;
        int slow = (tokens.size() > 2) ? std::stoi(tokens[2]) : 26;
        int signal = (tokens.size() > 3) ? std::stoi(tokens[3]) : 9;
        return runMACD(market_data, fast, slow, signal);
    }
    else if (strategy_name == "BOLLINGER") {
        int period = (tokens.size() > 1) ? std::stoi(tokens[1]) : 20;
        double std_dev_mult = (tokens.size() > 2) ? std::stod(tokens[2]) : 2.0;
        return runBollingerBands(market_data, period, std_dev_mult);
    }

    return runMovingAverageCross(market_data, 9, 21, false);
}

/**
 * @brief Executes the Moving Average Crossover strategy (SMA or EMA).
 */
BacktestResult BacktestingEngine::runMovingAverageCross(const MarketData& market_data, int fast_window, int slow_window, bool use_ema) {
    BacktestResult result = {0.0, 0, 0.0, 0.0, {}, {}, {}, {}};
    double initial_capital = 1000.0; 
    double current_capital = initial_capital;
    double crypto_amount = 0.0;
    
    double peak_capital = initial_capital;
    int winning_trades = 0;
    double entry_price = 0.0;
    bool in_position = false;

    const auto& prices = market_data.prices;
    const auto& timestamps = market_data.timestamps;

    if (fast_window >= slow_window) fast_window = slow_window - 1;
    if (fast_window < 1) fast_window = 1;

    if (prices.size() <= static_cast<size_t>(slow_window)) {
        result.equity_curve.push_back(initial_capital);
        if (!timestamps.empty()) result.timestamps.push_back(timestamps[0]);
        return result;
    }

    for (int i = 0; i < slow_window; ++i) {
        result.equity_curve.push_back(current_capital);
        result.timestamps.push_back(timestamps[i]);
    }

    std::vector<double> fast_ma(prices.size(), 0.0);
    std::vector<double> slow_ma(prices.size(), 0.0);

    if (use_ema) {
        calculateEMA(prices, fast_window, 0, fast_ma);
        calculateEMA(prices, slow_window, 0, slow_ma);
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

        int current_step = result.equity_curve.size();

        if (!in_position && prev_fast <= prev_slow && current_fast > current_slow) {
            crypto_amount = current_capital / prices[i];
            current_capital = 0.0;
            entry_price = prices[i];
            in_position = true;
            result.buy_signals.push_back(current_step);
        }
        else if (in_position && prev_fast >= prev_slow && current_fast < current_slow) {
            current_capital = crypto_amount * prices[i];
            crypto_amount = 0.0;
            in_position = false;
            result.trades_count++;
            if (prices[i] > entry_price) winning_trades++;
            result.sell_signals.push_back(current_step);
        }

        double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
        result.equity_curve.push_back(portfolio_value);
        result.timestamps.push_back(timestamps[i]);
        
        if (portfolio_value > peak_capital) peak_capital = portfolio_value;
        double current_drawdown = (peak_capital - portfolio_value) / peak_capital * 100.0;
        if (current_drawdown > result.max_drawdown) result.max_drawdown = current_drawdown;
    }

    finalizeMetrics(result, prices.back(), initial_capital, current_capital, crypto_amount, in_position, winning_trades);
    return result;
}

/**
 * @brief Executes the Relative Strength Index (RSI) strategy.
 */
BacktestResult BacktestingEngine::runRSI(const MarketData& market_data, int period, double overbought, double oversold) {
    BacktestResult result = {0.0, 0, 0.0, 0.0, {}, {}, {}, {}};
    double initial_capital = 1000.0; 
    double current_capital = initial_capital;
    double crypto_amount = 0.0;
    
    double peak_capital = initial_capital;
    int winning_trades = 0;
    double entry_price = 0.0;
    bool in_position = false;

    const auto& prices = market_data.prices;
    const auto& timestamps = market_data.timestamps;

    if (prices.size() <= static_cast<size_t>(period)) {
        result.equity_curve.push_back(initial_capital);
        if (!timestamps.empty()) result.timestamps.push_back(timestamps[0]);
        return result;
    }

    for (int i = 0; i < period; ++i) {
        result.equity_curve.push_back(current_capital);
        result.timestamps.push_back(timestamps[i]);
    }

    std::vector<double> rsi_values;
    calculateRSI(prices, period, rsi_values);

    for (size_t i = period; i < prices.size(); ++i) {
        double current_rsi = rsi_values[i];
        double prev_rsi = rsi_values[i-1];

        int current_step = result.equity_curve.size();

        if (!in_position && prev_rsi < oversold && current_rsi >= oversold) {
            crypto_amount = current_capital / prices[i];
            current_capital = 0.0;
            entry_price = prices[i];
            in_position = true;
            result.buy_signals.push_back(current_step);
        }
        else if (in_position && prev_rsi > overbought && current_rsi <= overbought) {
            current_capital = crypto_amount * prices[i];
            crypto_amount = 0.0;
            in_position = false;
            result.trades_count++;
            if (prices[i] > entry_price) winning_trades++;
            result.sell_signals.push_back(current_step);
        }

        double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
        result.equity_curve.push_back(portfolio_value);
        result.timestamps.push_back(timestamps[i]);
        
        if (portfolio_value > peak_capital) peak_capital = portfolio_value;
        double current_drawdown = (peak_capital - portfolio_value) / peak_capital * 100.0;
        if (current_drawdown > result.max_drawdown) result.max_drawdown = current_drawdown;
    }

    finalizeMetrics(result, prices.back(), initial_capital, current_capital, crypto_amount, in_position, winning_trades);
    return result;
}

/**
 * @brief Executes the MACD strategy.
 */
BacktestResult BacktestingEngine::runMACD(const MarketData& market_data, int fast_period, int slow_period, int signal_period) {
    BacktestResult result = {0.0, 0, 0.0, 0.0, {}, {}, {}, {}};
    double initial_capital = 1000.0; 
    double current_capital = initial_capital;
    double crypto_amount = 0.0;
    
    double peak_capital = initial_capital;
    int winning_trades = 0;
    double entry_price = 0.0;
    bool in_position = false;

    const auto& prices = market_data.prices;
    const auto& timestamps = market_data.timestamps;
    
    if (fast_period >= slow_period) fast_period = slow_period - 1;
    int warmup = slow_period + signal_period;

    if (prices.size() <= static_cast<size_t>(warmup)) {
        result.equity_curve.push_back(initial_capital);
        if (!timestamps.empty()) result.timestamps.push_back(timestamps[0]);
        return result;
    }

    std::vector<double> fast_ema(prices.size(), 0.0);
    std::vector<double> slow_ema(prices.size(), 0.0);
    calculateEMA(prices, fast_period, 0, fast_ema);
    calculateEMA(prices, slow_period, 0, slow_ema);

    std::vector<double> macd_line(prices.size(), 0.0);
    for (size_t i = slow_period - 1; i < prices.size(); ++i) {
        macd_line[i] = fast_ema[i] - slow_ema[i];
    }

    std::vector<double> signal_line(prices.size(), 0.0);
    int signal_start_idx = slow_period - 1;
    calculateEMA(macd_line, signal_period, signal_start_idx, signal_line);

    int trade_start_idx = signal_start_idx + signal_period;

    for (int i = 0; i < trade_start_idx; ++i) {
        result.equity_curve.push_back(current_capital);
        result.timestamps.push_back(timestamps[i]);
    }

    for (size_t i = trade_start_idx; i < prices.size(); ++i) {
        double current_macd = macd_line[i];
        double prev_macd = macd_line[i-1];
        double current_signal = signal_line[i];
        double prev_signal = signal_line[i-1];

        int current_step = result.equity_curve.size();

        if (!in_position && prev_macd <= prev_signal && current_macd > current_signal) {
            crypto_amount = current_capital / prices[i];
            current_capital = 0.0;
            entry_price = prices[i];
            in_position = true;
            result.buy_signals.push_back(current_step);
        }
        else if (in_position && prev_macd >= prev_signal && current_macd < current_signal) {
            current_capital = crypto_amount * prices[i];
            crypto_amount = 0.0;
            in_position = false;
            result.trades_count++;
            if (prices[i] > entry_price) winning_trades++;
            result.sell_signals.push_back(current_step);
        }

        double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
        result.equity_curve.push_back(portfolio_value);
        result.timestamps.push_back(timestamps[i]);
        
        if (portfolio_value > peak_capital) peak_capital = portfolio_value;
        double current_drawdown = (peak_capital - portfolio_value) / peak_capital * 100.0;
        if (current_drawdown > result.max_drawdown) result.max_drawdown = current_drawdown;
    }

    finalizeMetrics(result, prices.back(), initial_capital, current_capital, crypto_amount, in_position, winning_trades);
    return result;
}

/**
 * @brief Executes the Bollinger Bands strategy.
 */
BacktestResult BacktestingEngine::runBollingerBands(const MarketData& market_data, int period, double std_dev_multiplier) {
    BacktestResult result = {0.0, 0, 0.0, 0.0, {}, {}, {}, {}};
    double initial_capital = 1000.0; 
    double current_capital = initial_capital;
    double crypto_amount = 0.0;
    
    double peak_capital = initial_capital;
    int winning_trades = 0;
    double entry_price = 0.0;
    bool in_position = false;

    const auto& prices = market_data.prices;
    const auto& timestamps = market_data.timestamps;

    if (prices.size() <= static_cast<size_t>(period)) {
        result.equity_curve.push_back(initial_capital);
        if (!timestamps.empty()) result.timestamps.push_back(timestamps[0]);
        return result;
    }

    for (int i = 0; i < period; ++i) {
        result.equity_curve.push_back(current_capital);
        result.timestamps.push_back(timestamps[i]);
    }

    for (size_t i = period; i < prices.size(); ++i) {
        double sum = 0.0;
        for (int j = 0; j < period; ++j) {
            sum += prices[i - j - 1]; 
        }
        double sma = sum / period;

        double sq_sum = 0.0;
        for (int j = 0; j < period; ++j) {
            double diff = prices[i - j - 1] - sma;
            sq_sum += diff * diff;
        }
        double std_dev = std::sqrt(sq_sum / period);

        double upper_band = sma + (std_dev_multiplier * std_dev);
        double lower_band = sma - (std_dev_multiplier * std_dev);

        int current_step = result.equity_curve.size();

        if (!in_position && prices[i] <= lower_band) {
            crypto_amount = current_capital / prices[i];
            current_capital = 0.0;
            entry_price = prices[i];
            in_position = true;
            result.buy_signals.push_back(current_step);
        }
        else if (in_position && prices[i] >= upper_band) {
            current_capital = crypto_amount * prices[i];
            crypto_amount = 0.0;
            in_position = false;
            result.trades_count++;
            if (prices[i] > entry_price) winning_trades++;
            result.sell_signals.push_back(current_step);
        }

        double portfolio_value = in_position ? (crypto_amount * prices[i]) : current_capital;
        result.equity_curve.push_back(portfolio_value);
        result.timestamps.push_back(timestamps[i]);
        
        if (portfolio_value > peak_capital) peak_capital = portfolio_value;
        double current_drawdown = (peak_capital - portfolio_value) / peak_capital * 100.0;
        if (current_drawdown > result.max_drawdown) result.max_drawdown = current_drawdown;
    }

    finalizeMetrics(result, prices.back(), initial_capital, current_capital, crypto_amount, in_position, winning_trades);
    return result;
}

/**
 * @brief Finds the best strategy and parameters using an evolutionary Genetic Algorithm.
 * @param market_data The dataset to backtest against.
 * @return The optimal strategy payload string.
 */
std::string BacktestingEngine::optimizeParameters(const MarketData& market_data) {
    std::mt19937 rng(std::random_device{}());
    const int POP_SIZE = 40;
    const int GENERATIONS = 15;

    struct Chromosome {
        int strat, p1, p2, p3;
        double fitness;
    };

    auto generateRandom = [&rng]() -> Chromosome {
        return {
            std::uniform_int_distribution<int>(0, 4)(rng),
            std::uniform_int_distribution<int>(0, 200)(rng),
            std::uniform_int_distribution<int>(0, 200)(rng),
            std::uniform_int_distribution<int>(0, 200)(rng),
            -99999.0
        };
    };

    auto getPayload = [](const Chromosome& c) -> std::string {
        int s = c.strat % 5;
        int p1 = std::max(2, c.p1 % 50);
        int p2 = std::max(10, c.p2 % 200);
        int p3 = std::max(2, c.p3 % 50);
        if (s == 0) return "SMA_CROSS:" + std::to_string(p1) + ":" + std::to_string(p2);
        if (s == 1) return "EMA_CROSS:" + std::to_string(p1) + ":" + std::to_string(p2);
        if (s == 2) return "RSI:" + std::to_string(p1) + ":" + std::to_string(60 + (c.p2 % 30)) + ":" + std::to_string(10 + (c.p3 % 30));
        if (s == 3) return "MACD:" + std::to_string(p1) + ":" + std::to_string(p2) + ":" + std::to_string(p3);
        if (s == 4) return "BOLLINGER:" + std::to_string(std::max(5, c.p1 % 100)) + ":" + std::to_string(1.0 + (c.p2 % 30) / 10.0);
        return "SMA_CROSS:9:21";
    };

    std::vector<Chromosome> pop(POP_SIZE);
    for(int i=0; i<POP_SIZE; ++i) pop[i] = generateRandom();

    for (int gen = 0; gen < GENERATIONS; ++gen) {
        for (auto& c : pop) {
            if (c.fitness == -99999.0) c.fitness = runSimulation(market_data, getPayload(c)).profit;
        }
        std::sort(pop.begin(), pop.end(), [](const Chromosome& a, const Chromosome& b){ return a.fitness > b.fitness; });

        std::vector<Chromosome> nextPop;
        for(int i=0; i<10; ++i) nextPop.push_back(pop[i]); // Elitism: Keep top 10

        std::uniform_int_distribution<int> distTop(0, 9);
        std::uniform_real_distribution<double> prob(0.0, 1.0);

        while(nextPop.size() < POP_SIZE) {
            Chromosome p1 = pop[distTop(rng)];
            Chromosome p2 = pop[distTop(rng)];
            Chromosome child = p1;
            
            // Crossover
            if (prob(rng) > 0.5) child.p1 = p2.p1;
            if (prob(rng) > 0.5) child.p2 = p2.p2;
            if (prob(rng) > 0.5) child.p3 = p2.p3;
            
            // Mutation
            if (prob(rng) < 0.1) child.strat = std::uniform_int_distribution<int>(0,4)(rng);
            if (prob(rng) < 0.1) child.p1 = std::uniform_int_distribution<int>(0,200)(rng);
            if (prob(rng) < 0.1) child.p2 = std::uniform_int_distribution<int>(0,200)(rng);
            child.fitness = -99999.0;
            
            nextPop.push_back(child);
        }
        pop = nextPop;
    }

    for (auto& c : pop) {
        if (c.fitness == -99999.0) c.fitness = runSimulation(market_data, getPayload(c)).profit;
    }
    std::sort(pop.begin(), pop.end(), [](const Chromosome& a, const Chromosome& b){ return a.fitness > b.fitness; });
    
    return getPayload(pop[0]);
}