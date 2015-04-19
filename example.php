<?php
header('Content-Type: text/html; charset=UTF-8');

include('idna_convert.class.php');
$IDN = new idna_convert();

if (isset($_REQUEST['encode'])) {
    $decoded = isset($_REQUEST['decoded']) ? $_REQUEST['decoded'] : '';
    $encoded = $IDN->encode($decoded);
}
if (isset($_REQUEST['decode'])) {
    $encoded = isset($_REQUEST['encoded']) ? $_REQUEST['encoded'] : '';
    $decoded = $IDN->decode($encoded);
}

if (!isset($encoded)) $encoded = '';
if (!isset($decoded)) $decoded = '';

?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Punycode Converter</title>
<meta name="author" content="blue birdy">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<style type="text/css">
body {
    color: rgb(0, 0, 0);
    background-color: rgb(255, 255, 255);
    font-size: 10pt;
    font-family: Verdana, Helvetica, Sans-Serif;
}

body, form {
    margin: 0px;
}

form {
    display: inline;
}

input {
    font-size: 8pt;
    font-family: Verdana, Helvetica, Sans-Serif;
}

#mitte {
    text-align: center;
    vertical-align: middle;
}

#round {
    background-color: rgb(230, 230, 240);
    border: 1px solid black;
    text-align: center;
    vertical-align: middle;
    padding: 10px;
}

.thead {
    font-size: 9pt;
    font-weight: bold;
}

#copy {
    font-size: 8pt;
    color: rgb(60, 60, 80);
}

#subhead {
    font-size: 8pt;
}

#bla {
    font-size: 8pt;
    text-align: left;
}
</style>
</head>
<body>
<table width="750" border="0" cellpadding="50" cellspacing="0">
<tr>
 <td id="mitte">
  <div id="round">
   <strong>PHP IDNA Converter</strong><br />
   <span id="subhead">
    See <a href="http://faqs.org/rfcs/rfc3490.html" title="IDNA" target="_blank">RFC3490</a>,
    <a href="http://faqs.org/rfcs/rfc3491.html" title="Nameprep, a Stringprep profile" target="_blank">RFC3491</a>,
    <a href="http://faqs.org/rfcs/rfc3492.html" title="Punycode" target="_blank">RFC3492</a> and
    <a href="http://faqs.org/rfcs/rfc3454.html" title="Stringprep" target="_blank">RFC3454</a><br />
   </span>
   <br />
   <div id="bla">
   This converter allows you to transfer domain names between the encoded (Punycode) notation
   and the decoded (UTF-8) notation.<br />
   Just enter the domain name in the respective field and click on the button right beside it to have
   it converted. Please be aware, that you might even enter complete domain names (like j&#xFC;rgen-m&#xFC;ller.de),
   but without the protocol (<strong>DO NOT</strong> enter http://m&#xFC;ller.de) or an email address.<br />
   Since the underlying library is still buggy, we cannot guarantee its usefulness and correctness. You should
   always doublecheck the results given here by converting them back to the original form.<br />
   Any productive use is discouraged and prone to fail.<br />
   <br />
   Make sure, that your browser is capable of the <strong>UTF-8</strong> character encoding.<br />
   <br />
   For those of you interested in the PHP source of the underlying class, you might
   <a href="http://phlymail.de/cgi-bin/dlmanager.cgi?Goodies/idna_convert_024.zip">download it here</a> (latest version: 0.2.4).<br />
   Please be aware, that this class is provided as is and without any liability. Use at your own risk.<br />
   It is free for <strong>non-commercial</strong> purposes.<br />
   <br />
   Please feel free to report bugs and problems to: <a href="mailto:team@phlymail.de">team@phlymail.de</a> or
   <a href="mailto:idnaconv@code42.de">idnaconv@code42.de</a>.<br />
   <br />
   </div>
   <table border="0" cellpadding="2" cellspacing="2" align="center">
   <tr>
    <td class="thead" align="left">Original</td>
    <td class="thead" align="right">Punycode</td>
   </tr>
   <tr>
    <td>
     <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="GET">
      <input type="text" name="decoded" value="<?php echo $decoded; ?>" size="24" maxlength="255" />
      <input type="submit" name="encode" value="Encode &gt;&gt;" />
     </form>
    </td>
    <td>
     <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="GET">
      <input type="submit" name="decode" value="&lt;&lt; Decode" />
      <input type="text" name="encoded" value="<?php echo $encoded; ?>" size="24" maxlength="255" />
     </form>
    </td>
   </tr>
   </table><br />
   <br />
   <span id="copy">class: v0.2.7, npdata: v0.1.1; (c) blue birdy 2004<br />
    This service is brought to you by <a href="http://phlymail.de">http://phlymail.de</a></span>
</div>
 </td>
</tr>
</table>
</body>
</html>