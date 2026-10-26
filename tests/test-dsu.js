
const assert = require("node:assert/strict");
const { DisjointSet } = require("../frontend/js/dsu.js");

const dsu = new DisjointSet();

// Test 1: Register eight teams
for (let id = 1; id <= 8; id++) {
    dsu.makeSet(id);
}

assert.equal(dsu.getGroups().length, 8);
console.log("Test 1 passed: Teams registered");

// Test 2: Merge teams into four groups
dsu.union(1, 2);
dsu.union(3, 4);
dsu.union(5, 6);
dsu.union(7, 8);

assert.equal(dsu.getGroups().length, 4);
console.log("Test 2 passed: Teams grouped");

// Test 3: Verify team membership
assert.equal(dsu.find(1), dsu.find(2));
assert.equal(dsu.find(3), dsu.find(4));
assert.notEqual(dsu.find(1), dsu.find(3));
console.log("Test 3 passed: Group membership");

// Test 4: Every group contains two teams
const groups = dsu.getGroups();

assert.ok(groups.every(group => group.length === 2));
console.log("Test 4 passed: Group sizes");

// Test 5: All teams appear exactly once
const allTeams = groups.flat().sort((a, b) => a - b);

assert.deepEqual(allTeams, [1, 2, 3, 4, 5, 6, 7, 8]);
console.log("Test 5 passed: No teams missing or duplicated");

// Test 6: Duplicate union does not create another group
assert.equal(dsu.union(1, 2), false);
assert.equal(dsu.getGroups().length, 4);
console.log("Test 6 passed: Duplicate union");

// Test 7: Unknown team IDs are rejected
assert.throws(
    () => dsu.find(999),
    /Unknown team ID/
);
console.log("Test 7 passed: Invalid team ID");

// All tests passed
console.log("\nAll DSU tests passed!");
