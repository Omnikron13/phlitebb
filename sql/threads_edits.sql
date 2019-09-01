CREATE TABLE IF NOT EXISTS threads_edits(
    id       INTEGER PRIMARY KEY,
    title    TEXT    NOT NULL,
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
