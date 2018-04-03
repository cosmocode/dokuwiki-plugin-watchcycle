CREATE TABLE watchcycle (
    page TEXT PRIMARY KEY NOT NULL,
    maintainer TEXT NOT NULL,
    cycle INT NOT NULL,
    last_maintainer_rev INT NOT NULL,
    uptodate INT NOT NULL DEFAULT 1
);
