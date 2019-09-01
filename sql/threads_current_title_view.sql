CREATE VIEW IF NOT EXISTS threads_current_title_view AS
    WITH edits AS (
        SELECT MAX(id) id, title, threadID FROM threads_edits GROUP BY threadID
    )
    SELECT t.id id, IFNULL(e.title, t.title) title FROM threads t LEFT JOIN edits e ON t.id = e.threadID;
