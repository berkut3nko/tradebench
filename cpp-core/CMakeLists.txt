cmake_minimum_required(VERSION 3.20)
project(TradeBenchCore LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

set(PROTO_SRC_DIR "${CMAKE_CURRENT_SOURCE_DIR}/proto")
set(PROTO_IMPORT_DIR "${CMAKE_CURRENT_SOURCE_DIR}/../proto")
file(MAKE_DIRECTORY ${PROTO_SRC_DIR})

find_package(Threads REQUIRED)
find_package(PkgConfig REQUIRED)

pkg_check_modules(pqxx REQUIRED libpqxx)
pkg_check_modules(hiredis REQUIRED hiredis)
pkg_check_modules(grpc REQUIRED grpc++)
pkg_check_modules(protobuf REQUIRED protobuf)

find_package(GTest REQUIRED)

find_program(PROTOC_EXECUTABLE protoc)
find_program(GRPC_CPP_PLUGIN_EXECUTABLE grpc_cpp_plugin)

set(PROTO_FILE "${PROTO_IMPORT_DIR}/analysis.proto")
set(PROTO_GEN_SRC "${PROTO_SRC_DIR}/analysis.pb.cc")
set(PROTO_GEN_HDR "${PROTO_SRC_DIR}/analysis.pb.h")
set(GRPC_GEN_SRC "${PROTO_SRC_DIR}/analysis.grpc.pb.cc")
set(GRPC_GEN_HDR "${PROTO_SRC_DIR}/analysis.grpc.pb.h")

add_custom_command(
    OUTPUT "${PROTO_GEN_SRC}" "${PROTO_GEN_HDR}" "${GRPC_GEN_SRC}" "${GRPC_GEN_HDR}"
    COMMAND ${PROTOC_EXECUTABLE}
    ARGS --cpp_out "${PROTO_SRC_DIR}"
         --grpc_out "${PROTO_SRC_DIR}"
         --plugin=protoc-gen-grpc=${GRPC_CPP_PLUGIN_EXECUTABLE}
         -I "${PROTO_IMPORT_DIR}"
         "${PROTO_FILE}"
    DEPENDS "${PROTO_FILE}"
)

include_directories(include)
include_directories(${PROTO_SRC_DIR})
include_directories(${pqxx_INCLUDE_DIRS})
include_directories(${grpc_INCLUDE_DIRS})
include_directories(${protobuf_INCLUDE_DIRS})

# Library for shared logic (so both main and tests can use it)
add_library(core_logic STATIC 
    src/database.cpp
    src/engine.cpp
    src/grpc_service.cpp
    ${PROTO_GEN_SRC}
    ${GRPC_GEN_SRC}
)

# Link using the variables populated by pkg-config
target_link_libraries(core_logic 
    PUBLIC 
    ${pqxx_LIBRARIES}
    ${hiredis_LIBRARIES}
    ${grpc_LIBRARIES}
    ${protobuf_LIBRARIES}
    Threads::Threads
)

# Main Executable
add_executable(tradebench_core src/main.cpp)
target_link_libraries(tradebench_core PRIVATE core_logic)

# Unit Tests Executable
enable_testing()
add_executable(run_tests tests/unit_tests.cpp)
target_link_libraries(run_tests 
    PRIVATE 
    core_logic
    GTest::GTest 
    GTest::Main 
)

add_test(NAME CoreUnitTests COMMAND run_tests)