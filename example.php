<?php
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
   <strong>IDNA Converter (<a href="http://faqs.org/rfcs/rfc3492.html" target="_blank">RFC3492</a>; Punycode)</strong><br />
   <br />
   <div id="bla">
   This converter allows you to transfer domain names between the encoded (Punycode) notation
   and the decoded (8bit) notation.<br />
   Just enter the domain name in the respective field and click on the button right beside it to have
   it converted. Please be aware, that you might even enter complete domain names (like jürgen-müller.de),
   but neither should you enter the protocol (<strong>DO NOT</strong> enter http://müller.de) nor
   an email address.<br />
   Since the underlying library is in a quite early state of development we cannot guarantee its
   usefulness and correctness. You should always doublecheck the results given here by converting them
   back to the original form.<br />
   <br />
   For those of you interested in the PHP source of the underlying class, you might
   <a href="http://phlymail.de/cgi-bin/dlmanager.cgi?Goodies/idna_convert_010.zip">download it here</a>.<br />
   Please be aware, that this class is provided as is and without any liability. Use at your own risk.<br />
   <br />
   Please feel free to report bugs and problems to: <a href="mailto:team@phlymil.de">team@phlymail.de</a><br />
   <br />
   Have fun! :)<br />
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
   <span id="copy">(c) blue birdy 2004<br />
    This service is brought to you by <a href="http://phlymail.de">http://phlymail.de</a></span>
</div>
 </td>
</tr>
</table>
</body>
</html>



