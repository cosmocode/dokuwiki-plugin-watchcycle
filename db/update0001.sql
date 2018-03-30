CREATE TABLE watchcycle (
    pageid TEXT PRIMARY KEY NOT NULL,
    maintainer TEXT NOT NULL,
    cycle INT NOT NULL,
    last_maintainer_rev INT NOT NULL
);
