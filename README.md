# IDNA Convert - pure PHP IDNA converter

Project homepage: <http://idnaconv.net><br>
by Matthias Sommerfeld <matthias.sommerfeld@algo26.de><br>

## Introduction

The class IdnaConvert allows to convert internationalized domain names (see RFC 3490, 3491, 3492 and 3454 for details) as they can be used with various registries worldwide to be translated between their original (localized) form and their encoded form as it will be used in the DNS (Domain Name System).

The class provides two public methods, encode() and decode(), which do exactly what you would expect them to do. You are allowed to use complete domain names, simple strings and complete email addresses as well. That means, that you might use any of the following notations:

- www.nörgler.com
- xn--nrgler-wxa
- xn--brse-5qa.xn--knrz-1ra.info

Errors, incorrectly encoded or invalid strings will lead to either a FALSE response (when in strict mode) or to only partially converted strings.  
You can query the occurred error by calling the method get_last_error().

Unicode strings are expected to be either UTF-8 strings, UCS-4 strings or UCS-4 arrays. The default format is UTF-8. For setting different encodings, you can call the method setParams() - please see the inline documentation for details.  
ACE strings (the Punycode form) are always 7bit ASCII strings.

## Installation

### Via Composer

```
composer require algo26-matthias/idna-convert
```

### Official ZIP Package

The official ZIP packages are discontinued. Stick to Composer or Github to acquire your copy, please.

## Examples

### Example 1. 

Say we wish to encode the domain name nörgler.com:

```php
<?php  
// Include the class
use Algo26\IdnaConvert\IdnaConvert;
// Instantiate it
$IDN = new IdnaConvert();
// The input string, if input is not UTF-8 or UCS-4, it must be converted before  
$input = utf8_encode('nörgler.com');  
// Encode it to its punycode presentation  
$output = $IDN->encode($input);  
// Output, what we got now  
echo $output; // This will read: xn--nrgler-wxa.com
```


### Example 2. 

We received an email from a punycoded domain and are willing to learn, how the domain name reads originally

```php
<?php  
// Include the class
use Algo26\IdnaConvert\IdnaConvert;
// Instantiate it
$IDN = new IdnaConvert();
// The input string  
$input = 'andre@xn--brse-5qa.xn--knrz-1ra.info';  
// Encode it to its punycode presentation  
$output = $IDN->decode($input);  
// Output, what we got now, if output should be in a format different to UTF-8  
// or UCS-4, you will have to convert it before outputting it  
echo utf8_decode($output); // This will read: andre@börse.knörz.info
```


### Example 3. 

The input is read from a UCS-4 coded file and encoded line by line. By appending the optional second parameter we tell enode() about the input format to be used

```php
<?php  
// Include the class
use Algo26\IdnaConvert\IdnaConvert;
// Instantiate it
$IDN = new IdnaConvert();
// Iterate through the input file line by line  
foreach (file('ucs4-domains.txt') as $line) {  
    echo $IDN->encode(trim($line), 'ucs4_string');  
    echo "\n";  
}
```


### Example 4. 

We wish to convert a whole URI into the IDNA form, but leave the path or query string component of it alone. Just using encode() would lead to mangled paths or query strings. Here the public method encode_uri() comes into play:

```php
<?php  
// Include the class
use Algo26\IdnaConvert\IdnaConvert;
// Instantiate it
$IDN = new IdnaConvert();
// The input string, a whole URI in UTF-8 (!)  
$input = 'http://nörgler:secret@nörgler.com/my_päth_is_not_ÄSCII/');  
// Encode it to its punycode presentation  
$output = $IDN->encodeUri($input);
// Output, what we got now  
echo $output; // http://nörgler:secret@xn--nrgler-wxa.com/my_päth_is_not_ÄSCII/
```


### Example 5. 

Per default, the class converts strings according to IDNA version 2008. To support IDNA 2003, the class needs to be invoked with an additional parameter. This can also be achieved on an instance.

```php
<?php  
// Include the class  
use Algo26\IdnaConvert\IdnaConvert;
// Instantiate it, switching to IDNA 2003, the original, now outdated standard
$IDN = new IdnaConvert(['idn_version' => 2003]);
// Sth. containing the German letter ß  
$input = 'meine-straße.de');  
// Encode it to its punycode presentation  
$output = $IDN->encodeUri($input);  
// Output, what we got now  
echo $output; // xn--meine-strae-46a.de  
// Switch back to IDNA 2008
$IDN->setIdnVersion(2003);
// Sth. containing the German letter ß  
$input = 'meine-straße.de');  
// Encode it to its punycode presentation  
$output = $IDN->encodeUri($input);
// Output, what we got now  
echo $output; // meine-strasse.de
```


## Encoding helper

In case you have strings in encodings other than ISO-8859-1 and UTF-8 you might need to translate these strings to UTF-8 before feeding the IDNA converter with it.
PHP's built in functions `utf8_encode()` and `utf8_decode()` can only deal with ISO-8859-1.  
Use the encoding helper class supplied with this package for the conversion. It requires either iconv, libiconv or mbstring installed together with one of the relevant PHP extensions. The functions you will find useful are
`toUtf8()` as a replacement for `utf8_encode()` and
`fromUtf8()` as a replacement for `utf8_decode()`.

Example usage:

```php
<?php  
use Algo26\IdnaConvert\IdnaConvert;
use Algo26\IdnaConvert\TranscodeIso;
$mystring = '<something in e.g. ISO-8859-15';  
$mystring = TranscodeIso::toUtf8($mystring, 'ISO-8859-15');
$IDN = new IdnaConvert();
echo $IDN->encode($mystring);
```


## UCTC &mdash; Unicode Transcoder

Another class you might find useful when dealing with one or more of the Unicode encoding flavours. It can transcode into each other:
- UCS-4 string / array  
- UTF-8  
- UTF-7  
- UTF-7 IMAP (modified UTF-7)  
All encodings expect / return a string in the given format, with one major exception: UCS-4 array is just an array, where each value represents one code-point in the string, i.e. every value is a 32bit integer value.

Example usage:

```php
<?php  
use Algo26\IdnaConvert\TranscodeUnicode;
$mystring = 'nörgler.com';  
echo TranscodeUnicode::convert($mystring, 'utf8', 'utf7imap');
```


## Contact the author

For questions, bug reports and security issues just send me an email.

algo26 Beratungs UG<br>
c/o Matthias Sommerfeld<br>
Wichertstr. 5<br>
D-10439 Berlin<br>
<br>
Germany<br>
<br>
mailto:matthias.sommerfeld@algo26.de

