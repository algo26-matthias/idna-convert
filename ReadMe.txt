*******************************************************************************
*                                                                             *
*                    IDNA Convert (idna_convert.class.php)                    *
*                                                                             *
* http://idnaconv.phlymail.de                         mailto:team@phlymail.de *
*******************************************************************************
* (c) 2004 phlyLabs, Berlin                                                   *
*******************************************************************************

Introduction
------------

The class idna_convert allows to convert internationalized domain names
(see RFC 3490, 3491, 3492 and 3454 for detials) as they can be used with various
registries worldwide to be translated between their original (localized) form
and their encoded form as it will be used in the DNS (Domain Name System).

The class provides two public methods, encode() and decode(), which do exactly
what you would expect them to do. You are allowed to use complete domain names,
simple strings and complete email addresses as well. That means, that you might
use any of the following notations:

- www.nörgler.com
- xn--nrgler-wxa
- xn--brse-5qa.xn--knrz-1ra.info

Errors, incorrectly encoded or invalid strings will lead to either a FALSE
response (when in strict mode) or to only partially converted strings.
You can query the occured error by calling the method get_last_error() when
using the PHP4 version or through exceptions when the PHP5 version is used.

Unicode strings are expected to be either UTF-8 strings, UCS-4 strings or UCS-4
arrays. The default format is UTF-8. For setting different encodings, you can
call the method setParams() - please see the inline documentation for details.
ACE strings (the Punycode form) are always 7bit ASCII strings.


Files
-----

idna_convert.class.php       - The actual class
idna_convert.class.php5.php  - A PHP5 version, contributed by Marcus Nix
example.php                  - An example web page for converting
ReadMe.txt                   - This file
LICENCE                      - The LGPL licence file

For using the class, you will have to either use idna_convert.class.php or
idna_convert.class.php5.php from your application.


Examples
--------

1. Say we wish to encode the domain name nörgler.com:

// Include the class
include_once('idna_convert.class.php');
// Instantiate it
$IDN = new Net_IDNA();
// The input string, if input is not UTF-8 or UCS-4, it must be converted before
$input = utf8_encode('nörgler.com');
// Encode it to its punycode presentation
$output = $IDN->encode($input);
// Output, what we got now
echo $output; // This will read: xn--nrgler-wxa.com


2. We received an email from a punycoded domain and are willing to learn, how
   the domain name reads originally

// Include the class
include_once('idna_convert.class.php');
// Instantiate it
$IDN = new Net_IDNA();
// The input string
$input = 'andre@xn--brse-5qa.xn--knrz-1ra.info';
// Encode it to its punycode presentation
$output = $IDN->decode($input);
// Output, what we got now, if output should be in a format different to UTF-8
// or UCS-4, you will have to convert it before outputting it
echo utf8_decode($output); // This will read: andre@börse.knürz.info


3. The input is read from a UCS-4 coded file and encoded line by line. By
   appending the optional second parameter we tell enode() about the input
   format to be used

// Include the class
include_once('idna_convert.class.php');
// Instantiate it
$IDN = new Net_IDNA();
// Iterate through the input file line by line
foreach (file('ucs4-domains.txt') as $line) {
    echo $IDN->encode(trim($line), 'ucs4_string');
    echo "\n";
}


Contact us
----------

In case of errors, bugs, questions, wishes, please don't hesitate to contact us
under the email address above.

The team of
phlymail.de