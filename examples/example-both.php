<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
	<title>ezUser registration page example - PHP/Object</title>
</head>

<body>
<?php
	require_once '../ezUser.php';
	ezUserUI::getContainer('controlpanel');
	echo '<br style="clear:left;" /><hr />';
	ezUserUI::getContainer('account');
?>
</body>

</html>
