-- fix in case data was corrupted by wrong type conversions
UPDATE watchcycle SET uptodate = 0 WHERE uptodate = "";
