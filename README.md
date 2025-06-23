# Nashe File Sharing Example

This is a simple Express.js app that lets you upload files and share them via a link. Uploaded files are stored in a folder structure based on the current date:

```
uploads/
  YEAR/
    MONTH/
      DAY/
        <folder name from upload>/
```

After uploading, you get a share link that lists the files in that specific folder and allows downloads without authentication. Each share page also includes a **Download All** button which downloads a ZIP archive of the whole folder.

## Usage

Install dependencies:

```bash
npm install
```

Start the server:

```bash
npm start
```

Then open [http://localhost:3000](http://localhost:3000) in your browser.
