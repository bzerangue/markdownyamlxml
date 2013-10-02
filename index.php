<?php

include_once('lib/frontmatter.php');
include_once('lib/markdown.php');

#
# INSPIRATION FROM Nick Dunn (in the Symphony CMS forum)
# "Convert a Directory of Markdown Text Files for Dynamic XML Datasource Use"
# http://getsymphony.com/discuss/thread/60701/#position-2
#
# AND FROM Stack Overflow
# http://stackoverflow.com/questions/8545010/php-reading-first-2-lines-of-file-into-variable-and-cylce-through-subfolders/8545451#8545451
#


#
# configuration
#

$path = 'content/.';
$fileFilter = '~\.(md|markdown)$~';
$pattern = '~^(?:\d: (.*))?(?:(?:\r\n|\n)(?:\d: (.*)))?~u';


#
# main
#

# init result array (the nice one)
$result = array();

# recursive iterator for files
$iterator = new RecursiveIteratorIterator(
               new RecursiveDirectoryIterator($path, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO), 
               RecursiveIteratorIterator::SELF_FIRST);

foreach($iterator as $path => $info)
{
    # filter out files that don't match
    if (!preg_match($fileFilter, $path)) continue;

    # get first two lines
    try
    {
        for
        (
            $maxLines = 2,
            $lines = '',
            $file = $info->openFile()
            ; 
            !$file->eof() && $maxLines--
            ; 
            $lines .= $file->fgets()
        );
        $lines = rtrim($lines, "\n");

        if (!strlen($lines)) # skip empty files 
            continue;
    }
    catch (RuntimeException $e)
    {
        continue; # files which are not readable are skipped.
    }

    # parse md file
    $r = preg_match($pattern, $lines, $matches);
    if (FALSE === $r)
    {
        throw new Exception('Regular expression failed.');
    }
    list(, $title, $description) = $matches + array('', '', '');

    # grow result array
    $result[dirname($path)][] = array($path, $title, $description);
}



#
# output
#

// adding Content Type
header("Content-type: text/xml");

// create a new XML document
$doc = new DOMDocument('1.0', 'utf-8');

$doc->formatOutput = true;

//$dirCounter = 0;

$currentdate = date("Y-m-d");
$currenttime = date("H:i");



$r = $doc->createElement('data');
$doc-> appendChild($r);

$r->setAttribute("date-created", $currentdate  );
$r->setAttribute("time-created", $currenttime  );

foreach ($result as $name => $dirs)
{
    $directoryname = $doc->createElement('directory');
    $r->appendChild($directoryname);
    $directoryname->setAttribute("name", basename($name) );
    $directoryname->setAttribute("path", $name );

    foreach ($dirs as $entry)
    {
        list($path, $title, $description) = $entry;
        $page = new FrontMatter($path);
        $text = file_get_contents($path);

        // fetching markdown content and appending as XML
        $fragment = $doc->createDocumentFragment();
        $fragment->appendXML(Markdown($page->fetch('content')));


        $fileentry = $doc->createElement('file');
        $directoryname->appendChild($fileentry);
        $fileentry->setAttribute("path", $path);
        $fileentry->setAttribute("name", basename($path));

            $meta = $doc->createElement('meta');
            $fileentry->appendChild($meta);
            foreach($page->data as $key => $value)
            {
                // You want to skip the content item right?
                if($key != 'content')
                {
                    // $key = title
                    // $value = $page->fetch('title')
                    $metatitle = $doc->createElement(ltrim($key));
                    $metatitle->appendChild( $doc->createTextNode( $page->fetch($key) ) );
                    $meta->appendChild($metatitle);
                }
            }
           $contentoutput = $doc->createElement('content');
           $contentoutput->appendChild($fragment);
           $fileentry->appendChild($contentoutput); 

    }

}

// get completed xml document
$xml_string = $doc->saveXML();

echo $xml_string;


/**
 * Build A XML Data Set
 *
 * @param array $data Associative Array containing values to be parsed into an XML Data Set(s)
 * @param string $startElement Root Opening Tag, default fx_request
 * @param string $xml_version XML Version, default 1.0
 * @param string $xml_encoding XML Encoding, default UTF-8
 * @return string XML String containig values
 * @return mixed Boolean false on failure, string XML result on success
 */
function buildXMLData($data, $startElement = 'data', $xml_version = '1.0', $xml_encoding = 'UTF-8'){
  if(!is_array($data)){
     $err = 'Invalid variable type supplied, expected array not found on line '.__LINE__." in Class: ".__CLASS__." Method: ".__METHOD__;
     trigger_error($err);
     if($this->_debug) echo $err;
     return false; //return false error occurred
  }
  $xml = new XmlWriter();
  $xml->openMemory();
  $xml->startDocument($xml_version, $xml_encoding);
  $xml->startElement($startElement);

  /**
   * Write XML as per Associative Array
   * @param object $xml XMLWriter Object
   * @param array $data Associative Data Array
   */
  function write(XMLWriter $xml, $data){
      foreach($data as $key => $value){
          if(is_array($value)){
              $xml->startElement($key);
              write($xml, $value);
              $xml->endElement();
              continue;
          }
          $xml->writeElement($key, $value);
      }
  }
  write($xml, $data);

  $xml->endElement();//write end element
  //Return the XML results
  return $xml->outputMemory(true); 
}


