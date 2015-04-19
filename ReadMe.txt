*******************************************************************************
*                                                                             *
*                    IDNA Convert (idna_convert.class.php)                    *
*                                                                             *
* http://idnaconv.phlymail.de                         mailto:team@phlymail.de *
*******************************************************************************
* (c) 2004 blue birdy, Berlin                                                 *
*******************************************************************************

Introduction
------------

The class idna_convert allows to convert internationalized domain names
(see RFC 3490 for detials) as they can be used with various registries worldwide
to be translated between their original (localized) form and their encoded form
as it will be used in the DNS (Domain Name System).

The class provides two public methods, encode() and decode(), which do exactly
what you would expect them to do. You are allowed to use complete domain names,
simple strings and complete email addresses as well. That means, that you might
use any of the following notations:

- www.nörgler.com
- xn--nrgler-wxa
- xn--brse-5qa.xn--knrz-1ra.info

The methods expect strings as their input and will return you strings. Errors,
incorrectly encoded or invalid strings will lead to a FALSE response.
You can query the occured error by calling the method get_last_error().


Files
-----

idna_convert.class.php   - The actual class
idna_convert.npdata.php  - Nameprep tables, included by the class
example.php              - An example web page for converting
ReadMe.txt               - This file
Licence.txt              - The licence

For using the class, you will have to copy idna_convert.class.php as well as
idna_convert.npdata.php to the same directory.


Examples
--------

1. Say we wish to encode the domain name nörgler.com:

// Include the class
include_once('idna_convert.class.php');
// Instantiate it
$IDN = new idna_convert();
// The input string
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
$IDN = new idna_convert();
// The input string
$input = 'andre@xn--brse-5qa.xn--knrz-1ra.info';
// Encode it to its punycode presentation
$output = $IDN->decode($input);
// Output, what we got now
echo utf8_decode($output); // This will read: andre@börse.knürz.info


We wish you much fun with that class and look forward to feedback from you,
wether this class is useful.
In case of errors, bugs, questions, wishes, please don't hesitate to contact us
under the email address above.

The team of
phlymail.de