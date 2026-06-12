// Copyright (c) 2009-2010 Satoshi Nakamoto
// Copyright (c) 2009-2022 The Bitcoin Core developers
// Distributed under the MIT software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.

#include <pow.h>

#include <arith_uint256.h>
#include <chain.h>
#include <primitives/block.h>
#include <uint256.h>

static int64_t PowTargetTimespanAtHeight(const Consensus::Params& params, int64_t height)
{
    if (params.nPowRetargetV2ActivationHeight > 0 &&
        height >= params.nPowRetargetV2ActivationHeight &&
        params.nPowRetargetV2Timespan > 0) {
        return params.nPowRetargetV2Timespan;
    }
    if (params.nPowRetargetActivationHeight > 0 &&
        height >= params.nPowRetargetActivationHeight &&
        params.nPowRetargetTimespan > 0) {
        return params.nPowRetargetTimespan;
    }
    return params.nPowTargetTimespan;
}

static int64_t DifficultyAdjustmentIntervalAtHeight(const Consensus::Params& params, int64_t height)
{
    return PowTargetTimespanAtHeight(params, height) / params.nPowTargetSpacing;
}

static void PowAdjustmentTimespanBounds(const Consensus::Params& params, int64_t height, int64_t nTargetTimespan, int64_t& smallest_timespan, int64_t& largest_timespan)
{
    int64_t factor_num = 4;
    int64_t factor_den = 1;
    if (params.nPowRetargetV3ActivationHeight > 0 &&
        height >= params.nPowRetargetV3ActivationHeight &&
        params.nPowRetargetV2ActivationHeight > 0 &&
        height >= params.nPowRetargetV2ActivationHeight &&
        params.nPowRetargetV2Timespan > 0 &&
        nTargetTimespan == params.nPowRetargetV2Timespan &&
        params.nPowRetargetV3MaxFactorNum > 0 &&
        params.nPowRetargetV3MaxFactorDen > 0) {
        factor_num = params.nPowRetargetV3MaxFactorNum;
        factor_den = params.nPowRetargetV3MaxFactorDen;
    }
    smallest_timespan = nTargetTimespan * factor_den / factor_num;
    largest_timespan = nTargetTimespan * factor_num / factor_den;
}

static unsigned int CalculateNextWorkRequiredForTimespan(const CBlockIndex* pindexLast, int64_t nFirstBlockTime, const Consensus::Params& params, int64_t nTargetTimespan, int64_t height)
{
    if (params.fPowNoRetargeting)
        return pindexLast->nBits;

    // Limit adjustment step
    int64_t smallest_timespan = 0;
    int64_t largest_timespan = 0;
    PowAdjustmentTimespanBounds(params, height, nTargetTimespan, smallest_timespan, largest_timespan);
    int64_t nActualTimespan = pindexLast->GetBlockTime() - nFirstBlockTime;
    if (nActualTimespan < smallest_timespan)
        nActualTimespan = smallest_timespan;
    if (nActualTimespan > largest_timespan)
        nActualTimespan = largest_timespan;

    // Retarget
    const arith_uint256 bnPowLimit = UintToArith256(params.powLimit);
    arith_uint256 bnNew;
    bnNew.SetCompact(pindexLast->nBits);
    bnNew *= nActualTimespan;
    bnNew /= nTargetTimespan;

    if (bnNew > bnPowLimit)
        bnNew = bnPowLimit;

    return bnNew.GetCompact();
}

unsigned int GetNextWorkRequired(const CBlockIndex* pindexLast, const CBlockHeader *pblock, const Consensus::Params& params)
{
    assert(pindexLast != nullptr);
    unsigned int nProofOfWorkLimit = UintToArith256(params.powLimit).GetCompact();
    const int nHeightNext = pindexLast->nHeight + 1;
    const int64_t nTargetTimespan = PowTargetTimespanAtHeight(params, nHeightNext);
    const int64_t nDifficultyAdjustmentInterval = DifficultyAdjustmentIntervalAtHeight(params, nHeightNext);

    // Only change once per difficulty adjustment interval
    if (nHeightNext % nDifficultyAdjustmentInterval != 0)
    {
        if (params.fPowAllowMinDifficultyBlocks)
        {
            // Special difficulty rule for testnet:
            // If the new block's timestamp is more than 2* 10 minutes
            // then allow mining of a min-difficulty block.
            if (pblock->GetBlockTime() > pindexLast->GetBlockTime() + params.nPowTargetSpacing*2)
                return nProofOfWorkLimit;
            else
            {
                // Return the last non-special-min-difficulty-rules-block
                const CBlockIndex* pindex = pindexLast;
                while (pindex->pprev && pindex->nHeight % nDifficultyAdjustmentInterval != 0 && pindex->nBits == nProofOfWorkLimit)
                    pindex = pindex->pprev;
                return pindex->nBits;
            }
        }
        return pindexLast->nBits;
    }

    // Go back by the active difficulty window. A one-block interval needs the
    // previous block as the timestamp anchor so the elapsed spacing is real.
    int nHeightFirst = pindexLast->nHeight - (nDifficultyAdjustmentInterval - 1);
    if (nDifficultyAdjustmentInterval == 1 && pindexLast->pprev) {
        nHeightFirst = pindexLast->pprev->nHeight;
    }
    assert(nHeightFirst >= 0);
    const CBlockIndex* pindexFirst = pindexLast->GetAncestor(nHeightFirst);
    assert(pindexFirst);

    return CalculateNextWorkRequiredForTimespan(pindexLast, pindexFirst->GetBlockTime(), params, nTargetTimespan, nHeightNext);
}

unsigned int CalculateNextWorkRequired(const CBlockIndex* pindexLast, int64_t nFirstBlockTime, const Consensus::Params& params)
{
    return CalculateNextWorkRequiredForTimespan(pindexLast, nFirstBlockTime, params, params.nPowTargetTimespan, pindexLast->nHeight + 1);
}

// Check that on difficulty adjustments, the new difficulty does not increase
// or decrease beyond the permitted limits.
bool PermittedDifficultyTransition(const Consensus::Params& params, int64_t height, uint32_t old_nbits, uint32_t new_nbits)
{
    if (params.fPowAllowMinDifficultyBlocks) return true;

    const int64_t nTargetTimespan = PowTargetTimespanAtHeight(params, height);
    const int64_t nDifficultyAdjustmentInterval = DifficultyAdjustmentIntervalAtHeight(params, height);

    if (height % nDifficultyAdjustmentInterval == 0) {
        int64_t smallest_timespan = 0;
        int64_t largest_timespan = 0;
        PowAdjustmentTimespanBounds(params, height, nTargetTimespan, smallest_timespan, largest_timespan);

        const arith_uint256 pow_limit = UintToArith256(params.powLimit);
        arith_uint256 observed_new_target;
        observed_new_target.SetCompact(new_nbits);

        // Calculate the largest difficulty value possible:
        arith_uint256 largest_difficulty_target;
        largest_difficulty_target.SetCompact(old_nbits);
        largest_difficulty_target *= largest_timespan;
        largest_difficulty_target /= nTargetTimespan;

        if (largest_difficulty_target > pow_limit) {
            largest_difficulty_target = pow_limit;
        }

        // Round and then compare this new calculated value to what is
        // observed.
        arith_uint256 maximum_new_target;
        maximum_new_target.SetCompact(largest_difficulty_target.GetCompact());
        if (maximum_new_target < observed_new_target) return false;

        // Calculate the smallest difficulty value possible:
        arith_uint256 smallest_difficulty_target;
        smallest_difficulty_target.SetCompact(old_nbits);
        smallest_difficulty_target *= smallest_timespan;
        smallest_difficulty_target /= nTargetTimespan;

        if (smallest_difficulty_target > pow_limit) {
            smallest_difficulty_target = pow_limit;
        }

        // Round and then compare this new calculated value to what is
        // observed.
        arith_uint256 minimum_new_target;
        minimum_new_target.SetCompact(smallest_difficulty_target.GetCompact());
        if (minimum_new_target > observed_new_target) return false;
    } else if (old_nbits != new_nbits) {
        return false;
    }
    return true;
}

bool CheckProofOfWork(uint256 hash, unsigned int nBits, const Consensus::Params& params)
{
    bool fNegative;
    bool fOverflow;
    arith_uint256 bnTarget;

    bnTarget.SetCompact(nBits, &fNegative, &fOverflow);

    // Check range
    if (fNegative || bnTarget == 0 || fOverflow || bnTarget > UintToArith256(params.powLimit))
        return false;

    // Check proof of work matches claimed amount
    if (UintToArith256(hash) > bnTarget)
        return false;

    return true;
}
