#include "tradebench_core.h"
#include <iostream>

DataRepository::DataRepository(const std::string& conn_str) : m_conn_str(conn_str) {}

bool DataRepository::checkConnection() const {
    try {
        pqxx::connection C(m_conn_str);
        return C.is_open();
    } catch (const std::exception& e) {
        std::cerr << "[Repo] DB Error: " << e.what() << std::endl;
        return false;
    }
}

MarketData DataRepository::fetchPrices(const std::string& pair, const std::string& timeframe, int64_t start_ts, int64_t end_ts) {
    MarketData data;
    try {
        pqxx::connection C(m_conn_str);
        pqxx::work W(C);
        
        std::string query = "SELECT EXTRACT(EPOCH FROM tick_time)::BIGINT, close_price FROM currency_data WHERE pair_name = " 
                          + W.quote(pair) 
                          + " AND timeframe = " + W.quote(timeframe)
                          + " AND tick_time >= to_timestamp(" + std::to_string(start_ts) + ")"
                          + " AND tick_time <= to_timestamp(" + std::to_string(end_ts) + ")"
                          + " ORDER BY tick_time ASC;";
        
        pqxx::result R = W.exec(query);
        for (auto row : R) {
            data.timestamps.push_back(row[0].as<long long>());
            data.prices.push_back(row[1].as<double>());
        }
    } catch (const std::exception& e) {
        std::cerr << "[Repo] Fetch Error: " << e.what() << std::endl;
    }
    return data;
}

void DataRepository::saveResult(const std::string& task_id, const std::string& result_data) {
    try {
        pqxx::connection C(m_conn_str);
        pqxx::work W(C);
        std::string sql_update = "UPDATE analysis_tasks SET status = 'COMPLETED' WHERE id = " + W.quote(task_id) + ";";
        W.exec(sql_update);
        std::string sql_insert = "INSERT INTO analysis_results (task_id, result_data) VALUES (" + W.quote(task_id) + ", " + W.quote(result_data) + "::jsonb);";
        W.exec(sql_insert);
        W.commit();
    } catch (const std::exception& e) {
        std::cerr << "[Repo] Save Error: " << e.what() << std::endl;
    }
}