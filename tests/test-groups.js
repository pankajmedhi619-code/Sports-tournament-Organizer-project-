const assert = require("node:assert/strict");
const { generateGroups } = require("../frontend/js/groups.js");

const teams = Array.from({ length: 10 }, (_, index) => ({
    id: index + 1,
    name: `Team ${index + 1}`
}));

const groups = generateGroups(teams, 4);

// Three balanced groups: 4, 3, 3 teams
assert.equal(groups.length, 3);
assert.deepEqual(
    groups.map(group => group.teamIds.length),
    [4, 3, 3]
);

// No group exceeds the maximum size
assert.ok(groups.every(group => group.teamIds.length <= 4));

// Every team appears exactly once
const groupedIds = groups
    .flatMap(group => group.teamIds)
    .sort((a, b) => a - b);

assert.deepEqual(groupedIds, teams.map(team => team.id));

// Empty input returns no groups
assert.deepEqual(generateGroups([], 4), []);

// Duplicate team IDs must be rejected
assert.throws(
    () => generateGroups([{ id: 1 }, { id: 1 }], 4),
    /Duplicate team ID/
);

// Invalid group sizes must be rejected
assert.throws(
    () => generateGroups(teams, 1),
    /at least 2/
);

console.log("All group-generation tests passed!");