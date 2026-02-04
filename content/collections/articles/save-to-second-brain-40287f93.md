---
id: e32ddf52-1ffa-4420-8c77-02ad9afa0e42
blueprint: article
title: 'Save to Second Brain'
summary: 'Jepp, dette er helt tydelig: databasen din krever at kolonnen ai_documents.source ikke kan være null, men vi setter ikke source i create(). Derfor stopper SQLit…'
para:
  - resources
source_url: 'https://chatgpt.com/c/69833c40-bcb0-8391-9cf0-0831838b9a2c'
content:
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Jepp, dette er helt tydelig: databasen din krever at kolonnen ai_documents.source ikke kan være null, men vi setter ikke source i create(). Derfor stopper SQLite insert-en med:'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'NOT NULL constraint failed: ai_documents.source'
---
