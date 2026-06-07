#include <openssl/sha.h>

#include <array>
#include <atomic>
#include <chrono>
#include <cstdint>
#include <cstring>
#include <iomanip>
#include <iostream>
#include <mutex>
#include <optional>
#include <sstream>
#include <string>
#include <thread>
#include <vector>

#include <boost/multiprecision/cpp_int.hpp>

using boost::multiprecision::cpp_int;

namespace {

struct GenesisParams {
    std::string network;
    std::string timestamp;
    std::vector<uint8_t> output_script;
    uint32_t nTime;
    uint32_t nBits;
    int32_t nVersion;
    int64_t reward_coins;
    uint32_t nNonce{0};
};

struct GenesisResult {
    std::string merkle_root_hex;
    std::string genesis_hash_hex;
    uint32_t nNonce;
    uint32_t nTime;
    bool passed_target;
};

std::string Hex(const std::vector<uint8_t>& v)
{
    std::ostringstream oss;
    oss << std::hex << std::setfill('0');
    for (uint8_t b : v) oss << std::setw(2) << static_cast<int>(b);
    return oss.str();
}

std::vector<uint8_t> ParseHex(const std::string& in)
{
    if (in.size() % 2 != 0) throw std::runtime_error("hex input must have even length");
    std::vector<uint8_t> out;
    out.reserve(in.size() / 2);
    for (size_t i = 0; i < in.size(); i += 2) {
        out.push_back(static_cast<uint8_t>(std::stoul(in.substr(i, 2), nullptr, 16)));
    }
    return out;
}

void AppendLE32(std::vector<uint8_t>& out, uint32_t v)
{
    out.push_back(static_cast<uint8_t>(v & 0xff));
    out.push_back(static_cast<uint8_t>((v >> 8) & 0xff));
    out.push_back(static_cast<uint8_t>((v >> 16) & 0xff));
    out.push_back(static_cast<uint8_t>((v >> 24) & 0xff));
}

void AppendLE64(std::vector<uint8_t>& out, uint64_t v)
{
    for (int i = 0; i < 8; ++i) out.push_back(static_cast<uint8_t>((v >> (i * 8)) & 0xff));
}

void AppendVarInt(std::vector<uint8_t>& out, uint64_t v)
{
    if (v < 0xfd) {
        out.push_back(static_cast<uint8_t>(v));
    } else if (v <= 0xffff) {
        out.push_back(0xfd);
        out.push_back(static_cast<uint8_t>(v & 0xff));
        out.push_back(static_cast<uint8_t>((v >> 8) & 0xff));
    } else {
        throw std::runtime_error("varint values above 0xffff not needed for this tool");
    }
}

std::array<uint8_t, 32> DoubleSHA256(const std::vector<uint8_t>& data)
{
    std::array<uint8_t, 32> first{};
    std::array<uint8_t, 32> second{};
    SHA256_CTX ctx{};
    SHA256_Init(&ctx);
    SHA256_Update(&ctx, data.data(), data.size());
    SHA256_Final(first.data(), &ctx);
    SHA256_Init(&ctx);
    SHA256_Update(&ctx, first.data(), first.size());
    SHA256_Final(second.data(), &ctx);
    return second;
}

std::string HashDisplayHex(const std::array<uint8_t, 32>& raw_hash)
{
    std::ostringstream oss;
    oss << std::hex << std::setfill('0');
    for (auto it = raw_hash.rbegin(); it != raw_hash.rend(); ++it) {
        oss << std::setw(2) << static_cast<int>(*it);
    }
    return oss.str();
}

cpp_int CompactToTarget(uint32_t bits)
{
    const uint32_t exponent = bits >> 24;
    const uint32_t mantissa = bits & 0x007fffffU;
    cpp_int target = mantissa;
    if (exponent <= 3) {
        target >>= 8 * (3 - exponent);
    } else {
        target <<= 8 * (exponent - 3);
    }
    return target;
}

cpp_int HashHexToInt(const std::string& hex_be)
{
    cpp_int value = 0;
    for (char c : hex_be) {
        value <<= 4;
        if (c >= '0' && c <= '9') value += c - '0';
        else if (c >= 'a' && c <= 'f') value += 10 + (c - 'a');
        else if (c >= 'A' && c <= 'F') value += 10 + (c - 'A');
        else throw std::runtime_error("invalid hex character");
    }
    return value;
}

std::vector<uint8_t> BuildCoinbaseTx(const GenesisParams& p)
{
    std::vector<uint8_t> script_sig;
    script_sig.reserve(6 + p.timestamp.size());
    script_sig.push_back(0x04);
    script_sig.push_back(0xff);
    script_sig.push_back(0xff);
    script_sig.push_back(0x00);
    script_sig.push_back(0x1d);
    script_sig.push_back(0x01);
    script_sig.push_back(0x04);
    if (p.timestamp.size() > 75) throw std::runtime_error("timestamp too long for canonical single-byte push");
    script_sig.push_back(static_cast<uint8_t>(p.timestamp.size()));
    script_sig.insert(script_sig.end(), p.timestamp.begin(), p.timestamp.end());

    std::vector<uint8_t> tx;
    AppendLE32(tx, 1); // nVersion
    AppendVarInt(tx, 1); // vin size
    tx.insert(tx.end(), 32, 0x00); // prevout hash
    AppendLE32(tx, 0xffffffff); // prevout n
    AppendVarInt(tx, script_sig.size());
    tx.insert(tx.end(), script_sig.begin(), script_sig.end());
    AppendLE32(tx, 0xffffffff); // sequence
    AppendVarInt(tx, 1); // vout size
    const uint64_t reward_sats = static_cast<uint64_t>(p.reward_coins) * 100000000ULL;
    AppendLE64(tx, reward_sats);
    AppendVarInt(tx, p.output_script.size());
    tx.insert(tx.end(), p.output_script.begin(), p.output_script.end());
    AppendLE32(tx, 0); // locktime
    return tx;
}

std::vector<uint8_t> BuildBlockHeader(const GenesisParams& p, const std::array<uint8_t, 32>& merkle_raw, uint32_t nonce)
{
    std::vector<uint8_t> header;
    header.reserve(80);
    AppendLE32(header, static_cast<uint32_t>(p.nVersion));
    header.insert(header.end(), 32, 0x00); // hashPrevBlock null
    header.insert(header.end(), merkle_raw.begin(), merkle_raw.end()); // merkle root in internal byte order
    AppendLE32(header, p.nTime);
    AppendLE32(header, p.nBits);
    AppendLE32(header, nonce);
    return header;
}

GenesisResult GenerateGenesis(const GenesisParams& params, int threads, int max_seconds)
{
    const auto coinbase_tx = BuildCoinbaseTx(params);
    const auto merkle_raw = DoubleSHA256(coinbase_tx);
    const std::string merkle_hex = HashDisplayHex(merkle_raw);
    const cpp_int target = CompactToTarget(params.nBits);

    std::atomic<bool> found{false};
    std::atomic<uint64_t> tries{0};
    std::mutex result_mutex;
    GenesisResult result{merkle_hex, "", 0, params.nTime, false};

    const auto start = std::chrono::steady_clock::now();

    auto worker = [&](uint32_t start_nonce, uint32_t step) {
        for (uint64_t nonce = start_nonce; nonce <= 0xffffffffULL && !found.load(std::memory_order_relaxed); nonce += step) {
            const auto header = BuildBlockHeader(params, merkle_raw, static_cast<uint32_t>(nonce));
            const auto raw_hash = DoubleSHA256(header);
            const std::string hash_hex = HashDisplayHex(raw_hash);
            const cpp_int hash_int = HashHexToInt(hash_hex);
            ++tries;
            if (hash_int <= target) {
                bool expected = false;
                if (found.compare_exchange_strong(expected, true)) {
                    std::lock_guard<std::mutex> lk(result_mutex);
                    result.genesis_hash_hex = hash_hex;
                    result.nNonce = static_cast<uint32_t>(nonce);
                    result.passed_target = true;
                }
                return;
            }
            if ((tries.load() % 5'000'000ULL) == 0) {
                const auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(std::chrono::steady_clock::now() - start).count();
                std::cerr << "[progress] " << params.network << " tries=" << tries.load() << " elapsed_s=" << elapsed << "\n";
                if (elapsed >= max_seconds) return;
            }
        }
    };

    std::vector<std::thread> pool;
    pool.reserve(static_cast<size_t>(threads));
    for (int i = 0; i < threads; ++i) {
        pool.emplace_back(worker, static_cast<uint32_t>(i), static_cast<uint32_t>(threads));
    }
    for (auto& t : pool) t.join();

    return result;
}

bool VerifyGenesis(const GenesisParams& p, const std::string& expected_merkle_hex, const std::string& expected_hash_hex)
{
    const auto coinbase_tx = BuildCoinbaseTx(p);
    const auto merkle_raw = DoubleSHA256(coinbase_tx);
    const std::string merkle_hex = HashDisplayHex(merkle_raw);
    if (merkle_hex != expected_merkle_hex) return false;

    const auto header = BuildBlockHeader(p, merkle_raw, p.nNonce);
    const auto raw_hash = DoubleSHA256(header);
    const std::string hash_hex = HashDisplayHex(raw_hash);
    if (hash_hex != expected_hash_hex) return false;

    const cpp_int target = CompactToTarget(p.nBits);
    const cpp_int hash_int = HashHexToInt(hash_hex);
    return hash_int <= target;
}

} // namespace

int main(int argc, char* argv[])
{
    if (argc < 2) {
        std::cerr << "usage:\n";
        std::cerr << "  hobbyhash_genesis mine <network> <nTime> <nBitsHex> <nVersion> <rewardCoins> <outputScriptHex> <threads> <maxSeconds>\n";
        std::cerr << "  hobbyhash_genesis verify <network> <nTime> <nNonce> <nBitsHex> <nVersion> <rewardCoins> <outputScriptHex> <expectedMerkleHex> <expectedHashHex>\n";
        return 1;
    }

    const std::string mode = argv[1];
    const std::string kTimestamp = "HobbyCash Coin 2026 - solo mining for home hashers";

    if (mode == "mine") {
        if (argc != 10) {
            std::cerr << "invalid mine argument count\n";
            return 1;
        }
        GenesisParams p{};
        p.network = argv[2];
        p.timestamp = kTimestamp;
        p.nTime = static_cast<uint32_t>(std::stoul(argv[3]));
        p.nBits = static_cast<uint32_t>(std::stoul(argv[4], nullptr, 16));
        p.nVersion = std::stoi(argv[5]);
        p.reward_coins = std::stoll(argv[6]);
        p.output_script = ParseHex(argv[7]);
        const int threads = std::stoi(argv[8]);
        const int max_seconds = std::stoi(argv[9]);

        const auto result = GenerateGenesis(p, threads, max_seconds);
        std::cout << "network=" << p.network << "\n";
        std::cout << "timestamp=" << p.timestamp << "\n";
        std::cout << "output_script=" << Hex(p.output_script) << "\n";
        std::cout << "nTime=" << result.nTime << "\n";
        std::cout << "nNonce=" << result.nNonce << "\n";
        std::cout << "nBits=0x" << std::hex << std::nouppercase << p.nBits << std::dec << "\n";
        std::cout << "nVersion=" << p.nVersion << "\n";
        std::cout << "reward=" << p.reward_coins << "\n";
        std::cout << "merkle_root=" << result.merkle_root_hex << "\n";
        std::cout << "genesis_hash=" << result.genesis_hash_hex << "\n";
        std::cout << "target_pass=" << (result.passed_target ? "PASS" : "FAIL") << "\n";
        return result.passed_target ? 0 : 2;
    }

    if (mode == "verify") {
        if (argc != 11) {
            std::cerr << "invalid verify argument count\n";
            return 1;
        }
        GenesisParams p{};
        p.network = argv[2];
        p.timestamp = kTimestamp;
        p.nTime = static_cast<uint32_t>(std::stoul(argv[3]));
        p.nNonce = static_cast<uint32_t>(std::stoul(argv[4]));
        p.nBits = static_cast<uint32_t>(std::stoul(argv[5], nullptr, 16));
        p.nVersion = std::stoi(argv[6]);
        p.reward_coins = std::stoll(argv[7]);
        p.output_script = ParseHex(argv[8]);
        const std::string expected_merkle = argv[9];
        const std::string expected_hash = argv[10];
        const bool pass = VerifyGenesis(p, expected_merkle, expected_hash);
        std::cout << "verify_network=" << p.network << "\n";
        std::cout << "verify_result=" << (pass ? "PASS" : "FAIL") << "\n";
        return pass ? 0 : 3;
    }

    std::cerr << "unknown mode: " << mode << "\n";
    return 1;
}
