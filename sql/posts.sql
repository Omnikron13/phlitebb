CREATE TABLE IF NOT EXISTS posts(
    id       INTEGER PRIMARY KEY,
    text     TEXT    NOT NULL,
    time     INTEGER NOT NULL,
    threadID INTEGER NOT NULL,
    userID   INTEGER NOT NULL,
    FOREIGN KEY (threadID) REFERENCES threads(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (userID) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
