cmake_minimum_required(VERSION 3.20)
project(TradeBenchCore LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

set(PROTO_SRC_DIR "${CMAKE_CURRENT_SOURCE_DIR}/proto")
file(MAKE_DIRECTORY ${PROTO_SRC_DIR})

find_package(Threads REQUIRED)
find_package(PkgConfig REQUIRED)
pkg_check_modules(pqxx REQUIRED libpqxx)
find_package(GTest REQUIRED)

include_directories(include)
include_directories(${PROTO_SRC_DIR})
include_directories(${pqxx_INCLUDE_DIRS})

set(SOURCES 
    src/main.cpp 
)

add_executable(tradebench_core ${SOURCES})

target_link_libraries(tradebench_core 
    PRIVATE 
    ${pqxx_LIBRARIES}
    Threads::Threads
)

enable_testing()

add_executable(run_tests tests/unit_tests.cpp)
target_link_libraries(run_tests 
    PRIVATE 
    GTest::GTest 
    GTest::Main 
    Threads::Threads
)

add_test(NAME CoreUnitTests COMMAND run_tests)