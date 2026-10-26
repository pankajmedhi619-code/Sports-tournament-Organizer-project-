(function (root, factory) {
    if (typeof module === "object" && module.exports) {
        const { DisjointSet } = require("./dsu.js");
        module.exports = factory(DisjointSet);
    } else {
        root.GroupManager = factory(root.DisjointSet);
    }
})(typeof globalThis !== "undefined" ? globalThis : this, function (DisjointSet) {
    if (typeof DisjointSet !== "function") {
        throw new Error("DisjointSet must be loaded before groups.js");
    }

    /**
     * Generate balanced tournament groups using DSU.
     * maxGroupSize is the maximum number of teams per group.
     */
    function generateGroups(teams, maxGroupSize = 4) {
        if (!Array.isArray(teams)) {
            throw new TypeError("Teams must be an array");
        }

        if (!Number.isInteger(maxGroupSize) || maxGroupSize < 2) {
            throw new Error("Group size must be an integer of at least 2");
        }

        if (teams.length === 0) {
            return [];
        }

        // Validate the team IDs before grouping.
        const teamIds = new Set();

        for (const team of teams) {
            if (!team || team.id === null || team.id === undefined) {
                throw new Error("Every team must have an ID");
            }

            if (teamIds.has(team.id)) {
                throw new Error(`Duplicate team ID: ${team.id}`);
            }

            teamIds.add(team.id);
        }

        const dsu = new DisjointSet();

        // Initially, each team belongs to its own set.
        for (const team of teams) {
            dsu.makeSet(team.id);
        }

        // Calculate the number of balanced groups.
        const groupCount = Math.ceil(teams.length / maxGroupSize);
        const baseSize = Math.floor(teams.length / groupCount);
        const extraTeams = teams.length % groupCount;

        let start = 0;

        for (let i = 0; i < groupCount; i++) {
            const currentSize = baseSize + (i < extraTeams ? 1 : 0);
            const currentTeams = teams.slice(start, start + currentSize);

            // Merge all teams in this group into one DSU set.
            const firstTeamId = currentTeams[0].id;

            for (let j = 1; j < currentTeams.length; j++) {
                dsu.union(firstTeamId, currentTeams[j].id);
            }

            start += currentSize;
        }

        // Convert DSU sets into a consistent output format.
        const order = new Map(
            teams.map((team, index) => [team.id, index])
        );

        const groups = dsu.getGroups()
            .map(ids => ({
                teamIds: ids.sort(
                    (a, b) => order.get(a) - order.get(b)
                )
            }))
            .sort(
                (a, b) => order.get(a.teamIds[0]) - order.get(b.teamIds[0])
            )
            .map((group, index) => ({
                groupId: index + 1,
                name: `Group ${index + 1}`,
                teamIds: group.teamIds
            }));

        return groups;
    }

    return { generateGroups };
});
