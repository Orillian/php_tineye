# Php_TinEye Library #

#### PHP7 library using the TinEye API. ####
* Note: This has been written in php7. I will make a branch that uses php 5.6+ shortly..ish.

This library is loosely based on the Node Javascript version of found at the following: 

https://github.com/TinEye/tineye_api_node

It implements all the same methods and returns data in a similar fashion.

It also takes some code from the following pastebin's:

http://pastebin.com/YbiHNG27
http://pastebin.com/84uH0M0R

* Note: The quick instructions below uses the sandbox keys and the meloncat.jpg file which is the only image usable in the sandbox.
 
## How to Use ##
 
```
require_once(TinEyeApi.php)

$api_url = "https://api.tineye.com/rest/";

/*These are the Sandbox keys*/
$private_key = "6mm60lsCNIB,FwOWjJqA80QZHh9BMwc-ber4u=t^";
$public_key = "LCkn,2K7osVwkX95K4Oy";
 
$TinEye = new TinEyeApi($api_url,$public_key,$private_key);
```
#### Get Remaining Searches #### 
```
$request_response = $TinEye->remainingSearches();
``` 
#### Get Image Count #### 
``` 
$request_response = $TinEye->imageCount();
```

#### Searching using an image URL #### 
```
$params = [ //optional
'offset' => 0,
'limit' => 10,
'sort' => 'score',
'order' => 'desc'
]

$request_response = $TinEye->searchUrl('https://www.tineye.com/images/meloncat.jpg', $params);
``` 
#### Searching using image data #### 
 
$imageFile = 'local/path/to/image/file/'. 'meloncat.jpg';
``` 
$request_response = $TinEye->searchData($imageFile, $params);
```
* Note: This is written using basic curl. All of it could be just as easily converted to guzzle or some such.
