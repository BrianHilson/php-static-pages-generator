# PHP Static Pages Generator

PHP Static Page Generator (PSPG) is a simple static page generator for PHP. Allows the quick creation of new pages with Markdown files. It can be used for a blog or for any pages of a website which could be static.

## What it does
It takes Markdown files, parses them into HTML, and creates a static page for each one.

## Getting started
I suggest downloading example-website.zip to look to see an example of how a website could be organized.

```bash
example-website
  ├── harry-potter-blog
  ├── includes
  │   ├── footer.php
  │   └── header.php
  ├── index.php
  └── static-pages-generator
      ├── markdown-files
      │   ├── five-things-you-didnt-know-about-dumbledore.md
      │   ├── top-ten-most-popular-spells-at-hogwarts.md
      │   └── voldemort-was-worse-than-you-thought.md
      ├── parsedown.php
      ├── static-page-template.php
      └── static-pages-generator.php
```

**website** is the root folder for the website. In PHP, you would reference this with `$_SERVER['DOCUMENT_ROOT']`.

**harry-potter-blog** is where the static pages will be generated.

**includes** contains files that will be included on static pages or others, like header and footer.

**index.php** is the normal index page for the website. In this case it also has the static page generating code, so anytime someone comes to the website, it will update the static pages if the source files have been updated.

**static-pages-generator** contains all the files related to generating static pages.
 - **markdown-files** is where you store the source files for static pages.
 - **parsedown.php** is the markdown parsing class, available here: [https://parsedown.org/](https://parsedown.org/)
 - **static-page-template.php** is the template for generating static pages.
 - **static-pages-generator.php** is the class that does the actual work of generating static pages.

You can test it out by spinning up a PHP server, with example-website as the document root. Here's how to do that in macOS or Linux, assuming you have PHP installed:

1. Unzip example-website.zip
2. `cd` to example-website
3. Run `php -S localhost:8000`, which spins up a PHP server.
4. Go to [http://localhost:8000/](http://localhost:8000/) in your browser.

The static page generating code is on index.php, so the work of generating static pages should be complete. The file structure will now look like this:

```bash
example-website
  ├── harry-potter-blog
  │   ├── five-things-you-didnt-know-about-dumbledore
  │   │   ├── .generated-static-page
  │   │   └── index.html
  │   ├── top-ten-most-popular-spells-at-hogwarts
  │   │   ├── .generated-static-page
  │   │   └── index.html
  │   └── voldemort-was-worse-than-you-thought
  │       ├── .generated-static-page
  │       └── index.html
  ├── includes
  │   ├── footer.php
  │   └── header.php
  ├── index.php
  └── static-pages-generator
      ├── all-blog-data.json
      ├── markdown-files
      │   ├── five-things-you-didnt-know-about-dumbledore.md
      │   ├── top-ten-most-popular-spells-at-hogwarts.md
      │   └── voldemort-was-worse-than-you-thought.md
      ├── parsedown.php
      ├── static-page-template.php
      └── static-pages-generator.php
```

The static pages have now been added to  **harry-potter-blog**. Notice that for each markdown file a folder is created with the same name as the markdown file, with an index page inside of it. There's also a hidden file, **.generated-static-page**. This serves as a marker that the folder was created by the PSPG. Only folders with this marker can be deleted by the PSPG.


