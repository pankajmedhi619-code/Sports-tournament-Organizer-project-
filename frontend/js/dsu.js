
class DisjointSet {
    constructor() {
        this.parent = new Map();
        this.size = new Map();
    }

    // Create a separate set for each team
    makeSet(teamId) {
        if (teamId === null || teamId === undefined) {
            throw new Error("Team ID is required");
        }

        if (this.parent.has(teamId)) {
            return;
        }

        this.parent.set(teamId, teamId);
        this.size.set(teamId, 1);
    }

    // Find a team's representative using path compression
    find(teamId) {
        if (!this.parent.has(teamId)) {
            throw new Error(`Unknown team ID: ${teamId}`);
        }

        if (this.parent.get(teamId) !== teamId) {
            this.parent.set(
                teamId,
                this.find(this.parent.get(teamId))
            );
        }

        return this.parent.get(teamId);
    }

    // Merge two sets using union by size
    union(teamA, teamB) {
        let rootA = this.find(teamA);
        let rootB = this.find(teamB);

        // Already in the same set
        if (rootA === rootB) {
            return false;
        }

        // Keep the larger set as the root
        if (this.size.get(rootA) < this.size.get(rootB)) {
            [rootA, rootB] = [rootB, rootA];
        }

        this.parent.set(rootB, rootA);

        this.size.set(
            rootA,
            this.size.get(rootA) + this.size.get(rootB)
        );

        this.size.delete(rootB);

        return true;
    }

    // Return all teams organized by their sets
    getGroups() {
        const groups = new Map();

        for (const teamId of this.parent.keys()) {
            const root = this.find(teamId);

            if (!groups.has(root)) {
                groups.set(root, []);
            }

            groups.get(root).push(teamId);
        }

        return Array.from(groups.values());
    }
}

// Make DSU available to other browser scripts
if (typeof window !== "undefined") {
    window.DisjointSet = DisjointSet;
}
// Export for Node.js tests
if (typeof module !== "undefined" && module.exports) {
    module.exports = { DisjointSet };
}
