---
name: document-generator
description: "Generate QR codes, PDF documents, Word files (.docx), and Excel spreadsheets (.xlsx)"
version: "1.0.0"
tools_required: ["generate_qr", "generate_pdf", "generate_docx", "generate_xlsx"]
---

# Document Generator Skill

## Overview
Generates various document and media formats:
- **QR codes** — image URL for any text or URL
- **PDF** — from HTML or plain text content
- **Word** — `.docx` file with headings, paragraphs, tables
- **Excel** — `.xlsx` spreadsheet with data rows

Generated files are saved to `storage/app/public/generated/` and accessible via a public URL. For QR codes, a direct image URL is returned.

## Instructions

### QR Code
Use `generate_qr` with:
- `content` — text or URL to encode (required)
- `size` — pixel size of output image (default: `300`, max: `1000`)

Returns a public URL to the QR image.

### PDF
Use `generate_pdf` with:
- `filename` — output file name without extension (required)
- `html` — HTML content to render as PDF (required)
- `title` — optional document title

Returns the public URL to download the PDF.

**Note:** Requires `barryvdh/laravel-dompdf` package (`composer require barryvdh/laravel-dompdf`).

### Word Document
Use `generate_docx` with:
- `filename` — output file name without extension (required)
- `title` — document title
- `content` — array of sections, each with `type` (`heading`, `paragraph`, `table`) and `text`/`data`

Returns the public URL to download the .docx file.

**Note:** Requires `phpoffice/phpword` package (`composer require phpoffice/phpword`).

### Excel Spreadsheet
Use `generate_xlsx` with:
- `filename` — output file name without extension (required)
- `sheet_name` — name of the sheet (default: `Sheet1`)
- `headers` — array of column header strings
- `rows` — array of arrays with row data

Returns the public URL to download the .xlsx file.

**Note:** Requires `phpoffice/phpspreadsheet` package (`composer require phpoffice/phpspreadsheet`).

## Examples

User: "Generate a QR code for my website flamingdragon.io"
→ `generate_qr` content="https://flamingdragon.io"

User: "Create a PDF invoice for client Mario Rossi — €500 for consulting"
→ `generate_pdf` filename="invoice-mario-rossi" html="<h1>Invoice</h1>..."

User: "Make an Excel with my team: Alice (dev), Bob (design), Carol (PM)"
→ `generate_xlsx` filename="team" headers=["Name","Role"] rows=[["Alice","dev"],["Bob","design"],["Carol","PM"]]
