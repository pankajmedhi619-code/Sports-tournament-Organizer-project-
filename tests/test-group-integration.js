
const assert = require("node:assert/strict");

const {
    generateAndSaveGroups
} = require("../frontend/js/groups.js");

const teams = Array.from({ length: 10 }, (_, index) => ({
    id: index + 1,
    name: `Team ${index + 1}`,
    sport: "Football"
}));

let savedRequest = null;

// Fake API for testing without a live PHP server.
const mockAPI = {
    async getTeams(tournamentId) {
        assert.equal(tournamentId, 42);
        return teams;
    },

    async saveGroups(tournamentId, groups, replace) {
        savedRequest = {
            tournamentId,
            groups,
            replace
        };

        return {
            tournament_id: tournamentId,
            groups
        };
    }
};

async function runTests() {
    const result = await generateAndSaveGroups(
        42,
        4,
        mockAPI
    );

    // Check balanced group generation.
    assert.deepEqual(
        result.groups.map(group => group.teamIds.length),
        [4, 3, 3]
    );

    // Check the correct tournament was saved.
    assert.equal(savedRequest.tournamentId, 42);

    // Check the backend receives the expected group structure.
    assert.ok(
        savedRequest.groups.every(
            group =>
                typeof group.name === "string" &&
                Array.isArray(group.teamIds)
        )
    );

    // Ensure the request replaces previous groups.
    assert.equal(savedRequest.replace, true);

    // Verify all ten teams occur exactly once.
    const groupedIds = result.groups
        .flatMap(group => group.teamIds)
        .sort((a, b) => a - b);

    assert.deepEqual(
        groupedIds,
        [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
    );

    console.log("All group integration tests passed!");
}

runTests().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
