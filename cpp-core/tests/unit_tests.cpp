#include <gtest/gtest.h>

/* Simple test to verify that the environment is working */
TEST(EnvironmentTest, BasicAssertions) {
    EXPECT_STRNE("hello", "world");
    EXPECT_EQ(7 * 6, 42);
}

/* @brief Entry point for running unit tests */
int main(int argc, char **argv) {
    ::testing::InitGoogleTest(&argc, argv);
    return RUN_ALL_TESTS();
}