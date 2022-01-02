<?php

// Substitute sensitive characters with html entities and trim.
function sanitize($value)
{
	if (!is_array($value)) {
		$replace = array("&" => "&amp;", "<" => "&lt;", ">" => "&gt;", "'" => "&#39;", "\"" => "&quot;", "\\" => "&#92;", "\x00" => "");
		return strtr(trim($value), $replace);
	} else {
		foreach ($value as $k => $v) $value[$k] = sanitize($v);
		return $value;
	}
}

// Replace sanitized sensitive characters with their raw values.
function desanitize($value)
{
	if (!is_array($value)) {
		$replace = array("&amp;" => "&", "&lt;" => "<", "&gt;" => ">", "&#39;" => "'", "&#039;" => "'", "&quot;" => "\"", "&#92;" => "\\", "&#092;" => "\\");
		return strtr($value, $replace);
	} else {
		foreach ($value as $k => $v) $value[$k] = desanitize($v);
		return $value;
	}
}



class Formatter {

var $output = "";
var $modes = array();

function Formatter()
{
	// Define the modes.
	$this->modes = array(
		"emoticons" => new Formatter_Emoticons($this),
		"quote" => new Formatter_Quote($this),
		"bold" => new Formatter_Bold($this),
		"italic" => new Formatter_Italic($this),
		"heading" => new Formatter_Heading($this),
		"superscript" => new Formatter_Superscript($this),
		"strikethrough" => new Formatter_Strikethrough($this),
		"link" => new Formatter_Link($this),
		"image" => new Formatter_Image($this),
//		"list" => new Formatter_List($this),
//		"fixedBlock" => new Formatter_Fixed_Block($this),
//		"fixedInline" => new Formatter_Fixed_Inline($this),
		"horizontalRule" => new Formatter_Horizontal_Rule($this),
		"specialCharacters" => new Formatter_Special_Characters($this),
		"whitespace" => new Formatter_Whitespace($this),
	);
}

// Add a formatter to the array of modes.
function addFormatter($name, $class)
{
	$this->modes = array($name => new $class($this)) + $this->modes;
}

// Revert formatting on $string using formatters defined in $formatters.
function revert($string, $formatters = false)
{
	// Work out which formatters are going to be used.
	if (is_array($formatters)) $formatters = array_intersect(array_keys($this->modes), $formatters);
	else $formatters = array_keys($this->modes);
	
	// Collect simple reversion patterns from each of the individual formatters, and run them together.
	// e.g. <b> -> &lt;b&gt;
	$translations = array();
	foreach ($formatters as $v) {
		if (isset($this->modes[$v]->revert) and is_array($this->modes[$v]->revert)) $translations += $this->modes[$v]->revert;
	}
	$string = strtr($string, $translations);

	// Run any more complex reversions.
	foreach ($formatters as $v) {
		if (method_exists($this->modes[$v], "revert")) $string = $this->modes[$v]->revert($string);
	}
	
	$string = rtrim($string);
	return $string;
}

// Get a list of specific mode names which apply to a mode category.
// For example, getModes(array("bold")) returns array("bold_tag_b", "bold_tag_strong", "bold_bbcode", "bold_wiki").
function getModes($modes, $exclude = false)
{
	$newModes = array();
	foreach ($modes as $mode) {
		if ($mode == $exclude) continue;
		if (isset($this->modes[$mode])) $newModes = array_merge($newModes, $this->modes[$mode]->modes);
		else $newModes[] = $mode;
	}
	return $newModes;
}

}


class Formatter_Whitespace {

var $formatter;
var $revert = array("<br/>" => "\n", "<p>" => "", "</p>" => "\n\n");

function Formatter_Whitespace(&$formatter)
{
	$this->formatter =& $formatter;
}

}


class Formatter_Bold {

var $modes = array("bold_tag_b", "bold_tag_strong", "bold_bbcode", "bold_wiki");
var $revert = array("<b>" => "[b]", "</b>" => "[/b]");

function Formatter_Bold(&$formatter)
{
	$this->formatter =& $formatter;
}

}


class Formatter_Italic {

var $formatter;
var $modes = array("italic_tag_i", "italic_tag_em", "italic_bbcode", "italic_wiki");
var $revert = array("<i>" => "[i]", "</i>" => "[/i]");

function Formatter_Italic(&$formatter)
{
	$this->formatter =& $formatter;
}

}


class Formatter_Strikethrough {

var $formatter;
var $modes = array("strike_html", "strike_bbcode", "strike_wiki");
var $revert = array("<del>" => "[s]", "</del>" => "[/s]");

function Formatter_Strikethrough(&$formatter)
{
	$this->formatter =& $formatter;
}

}


class Formatter_Superscript {
// and Subscript

var $formatter;
var $modes = array("superscript", "subscript");
var $revert = array("<sup>" => "", "</sup>" => "", "<sub>" => "", "</sub>" => "");

function Formatter_Superscript(&$formatter)
{
	$this->formatter =& $formatter;
}

}


class Formatter_Heading {

var $formatter;
var $modes = array("heading_html", "heading_bbcode", "heading_wiki");
var $revert = array("<h3>" => "[h]", "</h3>" => "[/h]\n\n");

function Formatter_Heading(&$formatter)
{
	$this->formatter =& $formatter;
}

}


class Formatter_Quote {

var $formatter;
var $modes = array("quote_html", "quote_bbcode");
// var $revert = array(
// 	"<blockquote>" => "\n[quote]",
// 	"<blockquote><p>" => "\n[/quote]",
// 	"</blockquote>" => "\n&lt;/blockquote&gt;\n\n",
// 	"</p></blockquote>" => "\n&lt;/blockquote&gt;\n\n",
// 	"<p><cite>" => "&lt;cite&gt;",
// 	"</cite></p>" => "&lt;/cite&gt;\n",
// );

function Formatter_Quote(&$formatter)
{
	$this->formatter =& $formatter;
}

function revert($string)
{
	$string = preg_replace("|<blockquote><cite>(.+?) \- <a href='post/(.*?)/' class='postLink'></a></cite>(?:\n*)|i", "\n[quote=$2:@$1]", $string);
	$string = preg_replace("|<blockquote><cite>(.+?)</cite>(?:\n*)|i", "\n[quote=$1]", $string);
	$string = preg_replace("|<blockquote>(?:\n*)|i", "\n[quote]", $string);
	$string = preg_replace("|(?:\n*?)</blockquote>|i", "[/quote]\n\n", $string);

	return $string;
}

}


// class Formatter_Fixed_Block {

// var $formatter;
// var $modes = array("pre_html_block", "code_html_block", "code_bbcode_block");
// var $revert = array("<pre>" => "[code]", "</pre>" => "[/code]\n\n");

// function Formatter_Fixed_Block(&$formatter)
// {
//	$this->formatter =& $formatter;
// }

// }


// class Formatter_Fixed_Inline {

// var $formatter;
// var $modes = array("pre_html_inline", "code_html_inline", "code_bbcode_inline");
// var $revert = array("<code>" => "[code]", "</code>" => "[/code]");

// function Formatter_Fixed_Inline(&$formatter)
// {
//	$this->formatter =& $formatter;
// }

// }


class Formatter_Link {

var $formatter;
var $modes = array("link_html", "link_bbcode", "link_wiki", "postLink", "conversationLink");

function Formatter_Link(&$formatter)
{
	$this->formatter =& $formatter;
}

// Revert all links to their formatting code.
function revert($string)
{
	$string = preg_replace("/<a href='mailto:(.*?)'>\\1<\/a>/", "$1", $string);
	//$string = preg_replace("`<a href='" . str_replace("?", "\?", makeLink("post", "(\d+)")) . "'[^>]*>(.*?)<\/a>`e", "'[post:$1' . ('$2' ? ' $2' : '') . ']'", $string);
	//$string = preg_replace("`<a href='" . str_replace("?", "\?", makeLink("(\d+)")) . "'[^>]*>(.*?)<\/a>`", "[conversation:$1 $2]", $string);
	$string = preg_replace("/<a href='(?:\w+:\/\/)?(.*?)'>\\1<\/a>/", "$1", $string);
	$string = preg_replace("/<a href='(.*?)'>(.*?)<\/a>/", "[url=$1]$2[/url]", $string);
		
	return $string;
}

}


class Formatter_Image {

var $formatter;
var $modes = array("image_html", "image_bbcode1", "image_bbcode2");

function Formatter_Image(&$formatter)
{
	$this->formatter =& $formatter;
}

// Revert image tags to their formatting code.
function revert($string)
{
	$string = preg_replace("/<img src='(.*?)'\/>/", "[img]$1[/img]", $string);
	return $string;
}

}


// class Formatter_List {

// var $formatter;
// var $modes = array("blockList");

// var $listStack = array();
// var $initialDepth = 0;

// function Formatter_List(&$formatter)
// {
//	$this->formatter =& $formatter;
// }


// Revert lists to formatting code: get a simple lexer to do the dirty work.
// var $output;
// var $listLevel = -1;
// var $listNumbers = array();
// var $firstItem = true;

// function revert($string)
// {

//	$string = preg_replace("|<ul>\s*?(.*?)\s*?</ul>|ie", '$this->ul("$1")."\n"', $string);
//	$string = preg_replace("|<ol>\s*?(.*?)\s*?</ol>|ie", '$this->ol("$1")."\n"', $string);
	
//	return $string;
// }

// function ul($string)
// {
//	$string = str_replace(array("<li>", "</li>"), array("- ", "\n"), $string);
//	return $string;
// }

// function ol($string)
// {
//	$i = 1;
//	$string = str_replace(array("</li>"), array("\n"), $string);
//	$string = preg_replace("|<li>|ie", '$i++.". "', $string);
//	return $string;
// }

// }


class Formatter_Horizontal_Rule {

var $formatter;
var $revert = array("<hr/>" => "");

function Formatter_Horizontal_Rule(&$formatter)
{
	$this->formatter =& $formatter;
}

}


class Formatter_Special_Characters {

var $formatter;
var $characters = array(
	"&lt;-&gt;" => "↔",
	"-&gt;" => "→",
	"&lt;-" => "←",
	"&lt;=&gt;" => "⇔",
	"=&gt;" => "⇒",
	"&lt;=" => "⇐",
	"&gt;&gt;" => "»",
	"&lt;&lt;" => "«",
	"(c)" => "©",
	"(tm)" => "™",
	"(r)" => "®",
	"--" => "–",
	"..." => "…"
);

function Formatter_Special_Characters(&$formatter)
{
	$this->formatter =& $formatter;
	$this->revert = array_flip($this->characters);
}

}



class Formatter_Emoticons {
	
var $formatter;
var $emoticons = array();
	
function Formatter_Emoticons(&$formatter)
{
	$this->formatter = &$formatter;
	
	// Define the emoticons.
	$this->emoticons[":)"] = "<img src='js/x.gif' style='background-position:0 0' alt=':)' class='emoticon'/>";
	$this->emoticons["=)"] = "<img src='js/x.gif' style='background-position:0 0' alt='=)' class='emoticon'/>";
	$this->emoticons[":D"] = "<img src='js/x.gif' style='background-position:0 -20px' alt=':D' class='emoticon'/>";
	$this->emoticons["=D"] = "<img src='js/x.gif' style='background-position:0 -20px' alt='=D' class='emoticon'/>";
	$this->emoticons["^_^"] = "<img src='js/x.gif' style='background-position:0 -40px' alt='^_^' class='emoticon'/>";
	$this->emoticons["^^"] = "<img src='js/x.gif' style='background-position:0 -40px' alt='^^' class='emoticon'/>";
	$this->emoticons[":("] = "<img src='js/x.gif' style='background-position:0 -60px' alt=':(' class='emoticon'/>";
	$this->emoticons["=("] = "<img src='js/x.gif' style='background-position:0 -60px' alt='=(' class='emoticon'/>";
	$this->emoticons["-_-"] = "<img src='js/x.gif' style='background-position:0 -80px' alt='-_-' class='emoticon'/>";
	$this->emoticons[";)"] = "<img src='js/x.gif' style='background-position:0 -100px' alt=';)' class='emoticon'/>";
	$this->emoticons["^_-"] = "<img src='js/x.gif' style='background-position:0 -100px' alt='^_-' class='emoticon'/>";
	$this->emoticons["~_-"] = "<img src='js/x.gif' style='background-position:0 -100px' alt='~_-' class='emoticon'/>";
	$this->emoticons["-_^"] = "<img src='js/x.gif' style='background-position:0 -100px' alt='-_^' class='emoticon'/>";
	$this->emoticons["-_~"] = "<img src='js/x.gif' style='background-position:0 -100px' alt='-_~' class='emoticon'/>";
	$this->emoticons["^_^;"] = "<img src='js/x.gif' style='background-position:0 -120px; width:18px' alt='^_^;' class='emoticon'/>";
	$this->emoticons["^^;"] = "<img src='js/x.gif' style='background-position:0 -120px; width:18px' alt='^^;' class='emoticon'/>";
	$this->emoticons[">_<"] = "<img src='js/x.gif' style='background-position:0 -140px' alt='&gt;_&lt;' class='emoticon'/>";
	$this->emoticons[":/"] = "<img src='js/x.gif' style='background-position:0 -160px' alt=':/' class='emoticon'/>";
	$this->emoticons["=/"] = "<img src='js/x.gif' style='background-position:0 -160px' alt='=/' class='emoticon'/>";
	$this->emoticons[":\\"] = "<img src='js/x.gif' style='background-position:0 -160px' alt=':&#92;' class='emoticon'/>";
	$this->emoticons["=\\"] = "<img src='js/x.gif' style='background-position:0 -160px' alt='=&#92;' class='emoticon'/>";
	$this->emoticons[":x"] = "<img src='js/x.gif' style='background-position:0 -180px' alt=':x' class='emoticon'/>";
	$this->emoticons["=x"] = "<img src='js/x.gif' style='background-position:0 -180px' alt='=x' class='emoticon'/>";
	$this->emoticons[":|"] = "<img src='js/x.gif' style='background-position:0 -180px' alt=':|' class='emoticon'/>";
	$this->emoticons["=|"] = "<img src='js/x.gif' style='background-position:0 -180px' alt='=|' class='emoticon'/>";
	$this->emoticons["'_'"] = "<img src='js/x.gif' style='background-position:0 -180px' alt='&#39;_&#39;' class='emoticon'/>";
	$this->emoticons["<_<"] = "<img src='js/x.gif' style='background-position:0 -200px' alt='&lt;_&lt;' class='emoticon'/>";
	$this->emoticons[">_>"] = "<img src='js/x.gif' style='background-position:0 -220px' alt='&gt;_&gt;' class='emoticon'/>";
	$this->emoticons["x_x"] = "<img src='js/x.gif' style='background-position:0 -240px' alt='x_x' class='emoticon'/>";
	$this->emoticons["o_O"] = "<img src='js/x.gif' style='background-position:0 -260px' alt='o_O' class='emoticon'/>";
	$this->emoticons["O_o"] = "<img src='js/x.gif' style='background-position:0 -260px' alt='O_o' class='emoticon'/>";
	$this->emoticons["o_0"] = "<img src='js/x.gif' style='background-position:0 -260px' alt='o_0' class='emoticon'/>";
	$this->emoticons["0_o"] = "<img src='js/x.gif' style='background-position:0 -260px' alt='0_o' class='emoticon'/>";
	$this->emoticons[";_;"] = "<img src='js/x.gif' style='background-position:0 -280px' alt=';_;' class='emoticon'/>";
	$this->emoticons[":'("] = "<img src='js/x.gif' style='background-position:0 -280px' alt=':&#39;(' class='emoticon'/>";
	$this->emoticons[":O"] = "<img src='js/x.gif' style='background-position:0 -300px' alt=':O' class='emoticon'/>";
	$this->emoticons["=O"] = "<img src='js/x.gif' style='background-position:0 -300px' alt='=O' class='emoticon'/>";
	$this->emoticons[":o"] = "<img src='js/x.gif' style='background-position:0 -300px' alt=':o' class='emoticon'/>";
	$this->emoticons["=o"] = "<img src='js/x.gif' style='background-position:0 -300px' alt='=o' class='emoticon'/>";
	$this->emoticons[":P"] = "<img src='js/x.gif' style='background-position:0 -320px' alt=':P' class='emoticon'/>";
	$this->emoticons["=P"] = "<img src='js/x.gif' style='background-position:0 -320px' alt='=P' class='emoticon'/>";
	$this->emoticons[";P"] = "<img src='js/x.gif' style='background-position:0 -320px' alt=';P' class='emoticon'/>";
	$this->emoticons[":["] = "<img src='js/x.gif' style='background-position:0 -340px' alt=':[' class='emoticon'/>";
	$this->emoticons["=["] = "<img src='js/x.gif' style='background-position:0 -340px' alt='=[' class='emoticon'/>";
	$this->emoticons[":3"] = "<img src='js/x.gif' style='background-position:0 -360px' alt=':3' class='emoticon'/>";
	$this->emoticons["=3"] = "<img src='js/x.gif' style='background-position:0 -360px' alt='=3' class='emoticon'/>";
	$this->emoticons["._.;"] = "<img src='js/x.gif' style='background-position:0 -380px; width:18px' alt='._.;' class='emoticon'/>";
	$this->emoticons["<(^.^)>"] = "<img src='js/x.gif' style='background-position:0 -400px; width:19px' alt='&lt;(^.^)&gt;' class='emoticon'/>";
	$this->emoticons["(>'.')>"] = "<img src='js/x.gif' style='background-position:0 -400px; width:19px' alt='(&gt;&#39;.&#39;)&gt;' class='emoticon'/>";
	$this->emoticons["(>^.^)>"] = "<img src='js/x.gif' style='background-position:0 -400px; width:19px' alt='(&gt;^.^)&gt;' class='emoticon'/>";
	$this->emoticons["-_-;"] = "<img src='js/x.gif' style='background-position:0 -420px; width:18px' alt='-_-;' class='emoticon'/>";
	$this->emoticons["(o^_^o)"] = "<img src='js/x.gif' style='background-position:0 -440px' alt='(o^_^o)' class='emoticon'/>";
	$this->emoticons["(^_^)/"] = "<img src='js/x.gif' style='background-position:0 -460px; width:19px' alt='(^_^)/' class='emoticon'/>";
	$this->emoticons[">:("] = "<img src='js/x.gif' style='background-position:0 -480px' alt='&gt;:(' class='emoticon'/>";
	$this->emoticons[">:["] = "<img src='js/x.gif' style='background-position:0 -480px' alt='&gt;:[' class='emoticon'/>";
	$this->emoticons["._."] = "<img src='js/x.gif' style='background-position:0 -500px' alt='._.' class='emoticon'/>";
	$this->emoticons["T_T"] = "<img src='js/x.gif' style='background-position:0 -520px' alt='T_T' class='emoticon'/>";
	$this->emoticons["XD"] = "<img src='js/x.gif' style='background-position:0 -540px' alt='XD' class='emoticon'/>";
	$this->emoticons["('<"] = "<img src='js/x.gif' style='background-position:0 -560px' alt='(&#39;&lt;' class='emoticon'/>";
	$this->emoticons["B)"] = "<img src='js/x.gif' style='background-position:0 -580px' alt='B)' class='emoticon'/>";
	$this->emoticons["XP"] = "<img src='js/x.gif' style='background-position:0 -600px' alt='XP' class='emoticon'/>";
	$this->emoticons[":S"] = "<img src='js/x.gif' style='background-position:0 -620px' alt=':S' class='emoticon'/>";
	$this->emoticons["=S"] = "<img src='js/x.gif' style='background-position:0 -620px' alt='=S' class='emoticon'/>";
	$this->emoticons[">:)"] = "<img src='js/x.gif' style='background-position:0 -640px' alt='&gt;:)' class='emoticon'/>";
	$this->emoticons[">:D"] = "<img src='js/x.gif' style='background-position:0 -640px' alt='&gt;:D' class='emoticon'/>";
}

// Convert emoticons back into their corresponding text entity.
function revert($string)
{
	return strtr($string, array_flip($this->emoticons));
}

}

?>