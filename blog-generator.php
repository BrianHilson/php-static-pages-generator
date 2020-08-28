<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/phplib/parsedown.php');

class BlogGenerator
{
  private $siteRoot;
  private $blogDir;
  private $markdownFileDir;
  private $templateFilepath;
  private $blogPostsDataFilepath;
  private $blogPostData;
  private $parsedown;

  public function __construct() {
    $this->siteRoot = $_SERVER['DOCUMENT_ROOT'];
  }

  public function SetBlogDir($blogDir) {
    if (is_dir($this->siteRoot . $blogDir)) {
      $this->blogDir = $this->siteRoot . $blogDir;
    } else {
      die('The directory passed into SetBlogDir() does not appear to be a valid directory.');
    }
  }

  public function SetMarkdownFileDir($markdownFileDir) {
    if (is_dir($this->siteRoot . $markdownFileDir)) {
      $this->markdownFileDir = $this->siteRoot . $markdownFileDir;
    } else {
      die('The directory passed into SetMarkdownFileDir() does not appear to be a valid directory.');
    }
  }

  public function SetTemplateFilePath($templateFilepath) {
    if (file_exists($this->siteRoot . $templateFilepath)) {
      $this->templateFilepath = $this->siteRoot . $templateFilepath;
    } else {
      die('The filepath passed into SetTemplateFile() does not appear to be a valid filepath.');
    }
  }

  public function SetBlogPostsDataFilepath($blogPostsDataFilepath) {
    $this->blogPostsDataFilepath = $this->siteRoot . $blogPostsDataFilepath;
  }

  public function GenerateBlogs() {
    if ($this->AllInputsAreSet()) {
      $this->parsedown = new Parsedown();
      $this->blogPostData = $this->GetCurrentBlogPostData();
      $markdownFilenames = $this->GetMarkdownFileNames();
      $this->DeleteBlogPostsWithMissingMarkdownFiles($markdownFilenames);

      foreach ($markdownFilenames as $markdownFilename) {
        if ($this->BlogPostNeedsToBeUpdated($markdownFilename)) {
          $thisBlogDirectory = $this->CreateBlogDirectoryPath($markdownFilename);
          $this->CreateBlogDirectoryIfNeeded($thisBlogDirectory);
          $this->UpdateBlogPostsData($markdownFilename);
          $blogPostContent = $this->CreateBlogPostContent($markdownFilename);
          $this->SaveBlogPostFile($thisBlogDirectory, $blogPostContent);
          $this->SaveBlogPostMarkerFile($thisBlogDirectory);
        }
      }

      $this->SaveBlogPostsDataFile();
    }
    else
    {
      die('The blog directory, markdown file directory, or template filepath has not been set.');
    }
  }

  private function AllInputsAreSet() {
    return $this->siteRoot && $this->blogDir && $this->markdownFileDir && $this->templateFilepath;
  }

  public function GetCurrentBlogPostData() {
    $currentBlogPostData = array();

    if (file_exists($this->blogPostsDataFilepath)) {
      $currentBlogPostDataJSON = file_get_contents($this->blogPostsDataFilepath);
      $currentBlogPostData = json_decode($currentBlogPostDataJSON, true);
    }

    return $currentBlogPostData;
  }

  private function GetMarkdownFileNames() {
    return array_slice(scandir($this->markdownFileDir), 2);
  }

  private function DeleteBlogPostsWithMissingMarkdownFiles($markdownFilenames) {
    $currentBlogPosts = $this->GetCurrentBlogPosts();

    foreach ($currentBlogPosts as $post) {
      if ($this->MatchingMarkdownFileNotFound($post, $markdownFilenames)) {
        $this->DeletePost($post);
        $this->DeletePostData($post);
      }
    }
  }

  private function GetCurrentBlogPosts() {
    $currentPosts = array();

    foreach ($this->blogPostData as $post) {
      $currentPosts[] = $post['metaData']['link'];
    }

    return $currentPosts;
  }

  private function MatchingMarkdownFileNotFound($post, $markdownFilenames) {
    $matchNotFound = true;

    foreach($markdownFilenames as $filename) {
      if($post === ('/' . substr($filename, 0, -3)))
      {
        $matchNotFound = false;
      }
    }

    return $matchNotFound;
  }

  private function DeletePost($post) {
    $postFile = $this->siteRoot . $post . '/index.html';
    $markerFile = $this->siteRoot . $post . '/.generated-blog-post';
    $postDir = $this->siteRoot . $post;

    if (file_exists($postFile) && file_exists($markerFile) && is_dir($postDir)) {
      unlink($postFile);
      unlink($markerFile);
      rmdir($postDir);
    }
  }

  private function DeletePostData($post) {
    $explodedPost = explode('/', $post);
    $postFilename = end($explodedPost) . '.md';
    unset($this->blogPostData[$postFilename]);
  }

  private function BlogPostNeedsToBeUpdated($markdownFilename) {
    $blogPostNeedsToBeUpdated = false;

    $markdownFilepath = $this->markdownFileDir . '/' . $markdownFilename;
    $blogPostFilepath = $this->blogDir . '/' . substr($markdownFilename, 0, -3) . '/index.html';

    if (file_exists($blogPostFilepath)) {
      if (filemtime($markdownFilepath) > filemtime($blogPostFilepath)) {
        $blogPostNeedsToBeUpdated = true;
      }
    } else {
      $blogPostNeedsToBeUpdated = true;
    }

    return $blogPostNeedsToBeUpdated;
  }

  private function CreateBlogDirectoryPath($markdownFilename) {
    if (substr($markdownFilename, -3) !== '.md') {
      die($markdownFilename . ' is not a valid markdown filename. Markdown files must end with .md.');
    } else {
      return $this->blogDir . '/' . substr($markdownFilename, 0, -3);
    }
  }

  private function CreateBlogDirectoryIfNeeded($thisBlogDirectory) {
    if (!is_dir($thisBlogDirectory)) {
      mkdir($thisBlogDirectory);
      chmod($thisBlogDirectory, 0755);
    }
  }

  private function UpdateBlogPostsData($markdownFilename) {
    $processedMarkdownFile = $this->ProcessMarkdownFileAsPHP($markdownFilename);
    $blogPostData = $this->GenerateBlogPostData($markdownFilename, $processedMarkdownFile);
    $this->UpdateCurrentBlogPostsData($markdownFilename, $blogPostData);
  }

  private function ProcessMarkdownFileAsPHP($markdownFilename) {
    ob_start();

    include($this->markdownFileDir . '/' . $markdownFilename);
    $processedMarkdownFile = ob_get_contents();

    ob_end_clean();

    return $processedMarkdownFile;
  }

  private function UpdateCurrentBlogPostsData($markdownFilename, $blogPostData) {
    $this->blogPostData[$markdownFilename] = $blogPostData;
  }

  private function GenerateBlogPostData($markdownFilename, $markdownFile) {
    $blogPostData = $this->SeperateMetaDataAndBody($markdownFile);
    $blogPostData = $this->AddAdditionalMetaData($blogPostData, $markdownFilename);
    $blogPostData = $this->ParsePostBodyAsMarkdown($blogPostData);

    return $blogPostData;
  }

  private function SeperateMetaDataAndBody($blogPost) {
    $seperatedBlogContent = array();
    $explodedPost = explode('---' . PHP_EOL, $blogPost);
    $metaDataLines = explode(PHP_EOL, $explodedPost[1]);

    foreach($metaDataLines as $line)
    {
      $metaDataKeyValuePair = $this->MetaDataLineToKeyValuePair($line);

      if ($metaDataKeyValuePair) {
        foreach ($metaDataKeyValuePair as $key => $value) {
          $seperatedBlogContent['metaData'][$key] = $value;
        }
      }
    }
    
    $seperatedBlogContent['body'] = $explodedPost[2];

    return $seperatedBlogContent;
  }

  private function MetaDataLineToKeyValuePair($line) {
    $keyValuePair = false;
    $positionOfColon = strpos($line, ':');

    if ($positionOfColon !== false) {
      $keyValuePair = array();
      $key = trim(substr($line, 0, $positionOfColon));
      $value = trim(substr($line, $positionOfColon + 1));
      $keyValuePair[$key] = $value;
    }

    return $keyValuePair;
  }

  private function AddAdditionalMetaData($blogPostData, $markdownFilename) {
    $blogPostData = $this->AddRelativeLink($blogPostData, $markdownFilename);
    $blogPostData = $this->AddEstimatedReadingTime($blogPostData);

    return $blogPostData;
  }

  private function AddRelativeLink($blogPostData, $markdownFilename) {
    $absoluteFilepath = $this->CreateBlogDirectoryPath($markdownFilename);
    $blogPostData['metaData']['link'] = $this->CreateRootRelativeFilepath($absoluteFilepath);

    return $blogPostData;
  }

  private function AddEstimatedReadingTime($blogPostData) {
    $setMinutesToRead = !$blogPostData['metaData']['noMinutesToRead'];

    if ($setMinutesToRead) {
      $numberOfWords = str_word_count($blogPostData['body']);
      $adultReadingWordsPerMinute = 250;
      $minutesToRead = ceil($numberOfWords / $adultReadingWordsPerMinute);
  
      $blogPostData['metaData']['minutesToRead'] = $minutesToRead;
    }

    return $blogPostData;
  }

  private function CreateRootRelativeFilepath($absoluteFilepath) {
    return str_replace($this->siteRoot, '', $absoluteFilepath);
  }

  private function ParsePostBodyAsMarkdown($blogPostData) {
    $blogPostData['body'] = $this->parsedown->text($blogPostData['body']);
    return $blogPostData;
  }

  private function CreateBlogPostContent($markdownFilename) {
    $blogPostData = $this->GetBlogPostData($markdownFilename);
    $blogPostCompleteContent = $this->AddBodyToTemplate($blogPostData);

    return $blogPostCompleteContent;
  }

  private function GetBlogPostData($markdownFilename) {
    $thisBlogPostData = $this->blogPostData[$markdownFilename];

    return $thisBlogPostData;
  }

  private function AddBodyToTemplate($blogPostData) {
    ob_start();

    include($this->templateFilepath);
    $blogPostCompleteContent = ob_get_contents();

    ob_end_clean();

    return $blogPostCompleteContent;
  }

  private function SaveBlogPostFile($thisBlogDirectory, $blogPostContent) {
    file_put_contents($thisBlogDirectory . '/index.html', $blogPostContent);
  }

  private function SaveBlogPostMarkerFile($thisBlogDirectory) {
    touch($thisBlogDirectory . '/.generated-blog-post');
  }

  private function SaveBlogPostsDataFile() {
    $blogPostsDataJson = json_encode($this->blogPostData);
    file_put_contents($this->blogPostsDataFilepath, $blogPostsDataJson);
  }

  public function UpdateAllBlogPosts() {
    $this->DeleteAllPostsAndData();
    $this->GenerateBlogs();
  }

  private function DeleteAllPostsAndData() {
    foreach ($this->blogPostData as $blogPost) {
      unlink($this->siteRoot . $blogPost['metaData']['link'] . '/index.html');
      unlink($this->siteRoot . $blogPost['metaData']['link'] . '/.generated-blog-post');
      rmdir($this->siteRoot . $blogPost['metaData']['link']);
    }
  }

  public function ExcludeHiddenPosts($blogData) {
    foreach($blogData as $index => &$blog)
    {
      if(isset($blog['metaData']['hidden']) && $blog['metaData']['hidden']) {
        unset($blogData[$index]);
      }
    }
    unset($blog);

    return $blogData;
  }

  public function SortBlogDataByDate($blogData, $sortOrder) {
    uasort($blogData, function($a, $b) use ($sortOrder) {
      if ($sortOrder == 'DESC') {
        return strtotime($a['metaData']['publish_date']) < strtotime($b['metaData']['publish_date']);
      } else if ($sortOrder == 'ASC') {
        return (strtotime($a['metaData']['publish_date'])) > strtotime($b['metaData']['publish_date']);
      }
    });

    return $blogData;
  }
}