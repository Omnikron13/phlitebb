CREATE TABLE IF NOT EXISTS posts_edits(
    id       INTEGER PRIMARY KEY,
    text     TEXT    NOT NULL,
    time     INTEGER NOT NULL,
    postID   INTEGER NOT NULL,
    userID   INTEGER NOT NULL,
    FOREIGN KEY (postID) REFERENCES threads(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (userID) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
