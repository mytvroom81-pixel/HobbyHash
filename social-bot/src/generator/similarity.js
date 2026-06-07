/**
 * Duplicate / near-duplicate content detection using word overlap (Jaccard similarity).
 */

function tokenize(text) {
  return text
    .toLowerCase()
    .replace(/https?:\/\/\S+/g, '')
    .replace(/[^a-z0-9\s]/g, ' ')
    .split(/\s+/)
    .filter((w) => w.length > 2);
}

function jaccardSimilarity(a, b) {
  const setA = new Set(tokenize(a));
  const setB = new Set(tokenize(b));
  if (setA.size === 0 && setB.size === 0) return 1;
  if (setA.size === 0 || setB.size === 0) return 0;

  let intersection = 0;
  for (const w of setA) {
    if (setB.has(w)) intersection++;
  }
  const union = setA.size + setB.size - intersection;
  return union === 0 ? 0 : intersection / union;
}

function isTooSimilar(newText, existingTexts, threshold = 0.65) {
  for (const existing of existingTexts) {
    const content = typeof existing === 'string' ? existing : existing.content;
    if (!content) continue;
    const sim = jaccardSimilarity(newText, content);
    if (sim >= threshold) {
      return { similar: true, score: sim, matched: content.slice(0, 80) };
    }
  }
  return { similar: false, score: 0 };
}

function normalizeForComparison(text) {
  return text
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .replace(/[^\w\s]/g, '')
    .trim();
}

function isExactDuplicate(newText, existingTexts) {
  const norm = normalizeForComparison(newText);
  for (const existing of existingTexts) {
    const content = typeof existing === 'string' ? existing : existing.content;
    if (normalizeForComparison(content) === norm) {
      return true;
    }
  }
  return false;
}

module.exports = {
  tokenize,
  jaccardSimilarity,
  isTooSimilar,
  isExactDuplicate,
  normalizeForComparison,
};
