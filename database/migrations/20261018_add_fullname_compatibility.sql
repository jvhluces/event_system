ALTER TABLE users
    ADD COLUMN fullname VARCHAR(255) NOT NULL DEFAULT '' AFTER lastname;

UPDATE users
SET fullname = TRIM(
    CONCAT_WS(
        ' ',
        NULLIF(TRIM(firstname), ''),
        NULLIF(TRIM(middlename), ''),
        NULLIF(TRIM(lastname), '')
    )
)
WHERE fullname = '';
