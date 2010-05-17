<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// --------------------------------------------------------------------

$plugin_info = array(
	'pi_name' => 'Smarty Pants [EE2]',
	'pi_version' => '1.0',
	'pi_author' => 'Michel Fortin (Quickly upgraded to EE2 by Micky Hulse [hulse.me])',
	'pi_author_url' => 'http://daringfireball.net/projects/smartypants/',
	'pi_description' => '[Expression Engine 2.0] SmartyPants: translates plain ASCII punctuation into smart typographic punctuation HTML entities.',
	'pi_usage' => Smarty_pants::usage()
);

// --------------------------------------------------------------------

/**
 * Smarty_pants Class
 * 
 * @package       ExpressionEngine
 * @category      Plugin
 * @author        Michel Fortin (Quickly upgraded to EE2 by Micky Hulse [hulse.me])
 * @copyright     Copyright (c) 2010, Michel Fortin
 * @link          http://daringfireball.net/projects/smartypants/
 */
 
class Smarty_pants {
	
	//--------------------------------------------------------------------------
	//
	// Configurables (optional):
	//
	//--------------------------------------------------------------------------
	
	# 1 => "--" for em-dashes; no en-dash support.
	# 2 => "---" for em-dashes; "--" for en-dashes.
	# 3 => "--" for em-dashes; "---" for en-dashes.
	# See docs for more configuration options.
	var $smartypants_attr = '1';
	var $sp_tags_to_skip = '<(/?)(?:pre|code|kbd|script|math)[\s>]';
	
	//--------------------------------------------------------------------------
	//
	// Do not edit past this point.
	//
	//--------------------------------------------------------------------------
	
	// ----------------------------------
	// Public class variables:
	// ----------------------------------
	
	var $return_data = '';
	
	/**
	 * Constructor
	 *
	 * @access     public
	 * @return     void
	 */
	
	function Smarty_pants($str = '')
	{
		
		# Performance Guidelines:
		# http://expressionengine.com/public_beta/docs/development/guidelines/performance.html
		# General Style and Syntax:
		# http://expressionengine.com/public_beta/docs/development/guidelines/general.html
		
		// ----------------------------------
		// Call super object:
		// ----------------------------------
		
		$this->EE =& get_instance();
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		$attr = '';
		
		// ----------------------------------
		// Passing data directly:
		// ----------------------------------
		
		if ($str == '') $str = $this->EE->TMPL->tagdata;
		
		// ----------------------------------
		// Fetch plugin parameters:
		// ----------------------------------
		
		$attr = ( ! $this->EE->TMPL->fetch_param('attr')) ? NULL : $this->EE->TMPL->fetch_param('attr');
		
		// ----------------------------------
		// Return data:
		// ----------------------------------
		
		$this->return_data = $this->_smarty_pants($str, $attr);
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Smarty Pants
	 * 
	 * @access	private
	 * @param	string (text to be parsed)
	 * @param	string (value of the smart_quotes="" attribute)
	 * @return	string
	 */
	
	function _smarty_pants($text, $attr = NULL)
	{
		
		// ----------------------------------
		// Method variables:
		// ----------------------------------
		
		# Options to specify which transformations to make:
		$do_stupefy = FALSE;
		$convert_quot = 0; // should we translate &quot; entities into normal quotes?
		
		// ----------------------------------
		// Check parameters:
		// ----------------------------------
		
		if ($attr == NULL) $attr = $this->smartypants_attr;
		
		// ----------------------------------
		// Parse attributes:
		// ----------------------------------
		
		# 0 : do nothing
		# 1 : set all
		# 2 : set all, using old school en- and em- dash shortcuts
		# 3 : set all, using inverted old school en and em- dash shortcuts
		# 
		# q : quotes
		# b : backtick quotes (``double'' only)
		# B : backtick quotes (``double'' and `single')
		# d : dashes
		# D : old school dashes
		# i : inverted old school dashes
		# e : ellipses
		# w : convert &quot; entities to " for Dreamweaver users
		
		if ($attr == '0')
		{
			# Do nothing.
			return $text;
		}
		else if ($attr == '1')
		{
			# Do everything, turn all options on.
			$do_quotes    = 1;
			$do_backticks = 1;
			$do_dashes    = 1;
			$do_ellipses  = 1;
		}
		else if ($attr == '2')
		{
			# Do everything, turn all options on, use old school dash shorthand.
			$do_quotes    = 1;
			$do_backticks = 1;
			$do_dashes    = 2;
			$do_ellipses  = 1;
		}
		else if ($attr == '3')
		{
			# Do everything, turn all options on, use inverted old school dash shorthand.
			$do_quotes    = 1;
			$do_backticks = 1;
			$do_dashes    = 3;
			$do_ellipses  = 1;
		}
		else if ($attr == '-1')
		{
			# Special "stupefy" mode.
			$do_stupefy   = 1;
			$do_quotes	  = FALSE;
			$do_backticks = FALSE;
			$do_dashes    = FALSE;
			$do_ellipses  = FALSE;
		}
		else
		{
			$chars = preg_split('//', $attr);
			foreach ($chars as $c)
			{
				if ($c == 'q')
				{
					$do_quotes = 1;
				}
				else if ($c == 'b')
				{
					$do_backticks = 1;
				}
				else if ($c == 'B')
				{
					$do_backticks = 2;
				}
				else if ($c == 'd') {
					$do_dashes = 1;
				}
				else if ($c == 'D') {
					$do_dashes = 2;
				}
				else if ($c == "i") {
					$do_dashes = 3;
				}
				else if ($c == 'e') {
					$do_ellipses = 1;
				}
				else if ($c == 'w') {
					$convert_quot = 1;
				}
				else
				{
					# Unknown attribute option, ignore.
				}
			}
		}
		
		$tokens = $this->_tokenize_html($text);
		$result = '';
		$in_pre = 0;  # Keep track of when we're inside <pre> or <code> tags.
		
		# This is a cheat, used to get some context
		# for one-character tokens that consist of 
		# just a quote char. What we do is remember
		# the last character of the previous text
		# token, to use as context to curl single-
		# character quote tokens correctly.
		$prev_token_last_char = '';
		
		foreach ($tokens as $cur_token)
		{
			
			if ($cur_token[0] == "tag")
			{
				
				# Don't mess with quotes inside tags.
				$result .= $cur_token[1];
				if (preg_match("@$this->sp_tags_to_skip@", $cur_token[1], $matches))
				{
					$in_pre = isset($matches[1]) && $matches[1] == '/' ? 0 : 1;
				}
				
			}
			else
			{
				
				$t = $cur_token[1];
				$last_char = substr($t, -1); // Remember last char of this token before processing.
				
				if ( ! $in_pre)
				{
					
					$t = $this->_process_escapes($t);
					
					if ($convert_quot)
					{
						$t = preg_replace('/&quot;/', '"', $t);
					}
					
					if ($do_dashes)
					{
						if ($do_dashes == 1) $t = $this->_educate_dashes($t);
						if ($do_dashes == 2) $t = $this->_educate_dashes_oldschool($t);
						if ($do_dashes == 3) $t = $this->_educate_dashes_oldschool_inverted($t);
					}
					
					if ($do_ellipses) $t = $this->_educate_ellipses($t);
					
					# Note: backticks need to be processed before quotes.
					if ($do_backticks)
					{
						$t = $this->_educate_backticks($t);
						if ($do_backticks == 2) $t = $this->_educate_single_backticks($t);
					}
					
					if ($do_quotes)
					{
						if ($t == "'")
						{
							# Special case: single-character ' token:
							if (preg_match('/\S/', $prev_token_last_char))
							{
								$t = "&#8217;";
							}
							else
							{
								$t = "&#8216;";
							}
						}
						else if ($t == '"')
						{
							# Special case: single-character " token:
							if (preg_match('/\S/', $prev_token_last_char))
							{
								$t = "&#8221;";
							}
							else
							{
								$t = "&#8220;";
							}
						}
						else
						{
							# Normal case:                  
							$t = $this->_educate_quotes($t);
						}
					}
					
					if ($do_stupefy) $t = $this->_stupefy_entities($t);
					
				}
				
				$prev_token_last_char = $last_char;
				
				$result .= $t;
			}
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $result;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Tokenize HTML
	 * 
	 * @access	private
	 * @param	string
	 * @return	array
	 */
	
	function _tokenize_html($str)
	{
		
		#   Parameter:  String containing HTML markup.
		#     Returns:  An array of the tokens comprising the input
		#               string. Each token is either a tag (possibly with nested,
		#               tags contained therein, such as <a href="<MTFoo>">, or a
		#               run of text between tags. Each element of the array is a
		#               two-element array; the first is either 'tag' or 'text';
		#               the second is the actual value.
		# 
		#   Regular expression derived from the _tokenize() subroutine in 
		#   Brad Choate's MTRegex plugin.
		#   <http://www.bradchoate.com/past/mtregex.php>
		
		$index = 0;
		$tokens = array();
		$depth = 6;
		$nested_tags = str_repeat('(?:<[a-z\/!$](?:[^<>]|',$depth) . str_repeat(')*>)', $depth);
		$match =
			"(?s:<!(?:--.*?--\s*)+>)|" .  # comment
			"(?s:<\?.*?\?>)|" .           # processing instruction
			"$nested_tags";               # nested tags
		
		$parts = preg_split("/($match)/", $str, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		foreach ($parts as $part) {
			$index++;
			if ($part != '')
			if ($index % 2) array_push($tokens, array('text', $part));
			else  array_push($tokens, array('tag', $part));
		}
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $tokens;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Educates string
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _educate_quotes($_)
	{
		
		#   Parameter:  String.
		#     Returns:  The string, with "educated" curly quote HTML entities.
		# 
		#    Example input: "Isn't this fun?"
		#   Example output: &#8220;Isn&#8217;t this fun?&#8221;
		
		# Make our own "punctuation" character class, because the POSIX-style
		# [:PUNCT:] is only available in Perl 5.6 or later:
		$punct_class = "[!\"#\\$\\%'()*+,-.\\/:;<=>?\\@\\[\\\\\]\\^_`{|}~]";
		
		# Special case if the very first character is a quote
		# followed by punctuation at a non-word-break. Close the quotes by brute force:
		$_ = preg_replace(
			array("/^'(?=$punct_class\\B)/", "/^\"(?=$punct_class\\B)/"),
			array('&#8217;',                 '&#8221;'), $_);
		
		# Special case for double sets of quotes, e.g.:
		# <p>He said, "'Quoted' words in a larger quote."</p>
		$_ = preg_replace(
			array("/\"'(?=\w)/",    "/'\"(?=\w)/"),
			array('&#8220;&#8216;', '&#8216;&#8220;'), $_);
		
		# Special case for decade abbreviations (the '80s):
		$_ = preg_replace("/'(?=\\d{2}s)/", '&#8217;', $_);
		
		$close_class = '[^\ \t\r\n\[\{\(\-]';
		$dec_dashes = '&#8211;|&#8212;';
		
		# Get most opening single quotes:
		$_ = preg_replace("{
			(
				\\s          |   # a whitespace char, or
				&nbsp;      |   # a non-breaking space entity, or
				--          |   # dashes, or
				&[mn]dash;  |   # named dash entities
				$dec_dashes |   # or decimal entities
				&\\#x201[34];    # or hex
			)
			'                   # the quote
			(?=\\w)              # followed by a word character
			}x", '\1&#8216;', $_);
		
		# Single closing quotes:
		$_ = preg_replace("{
			($close_class)?
			'
			(?(1)|          # If $1 captured, then do nothing;
			  (?=\\s | s\\b)  # otherwise, positive lookahead for a whitespace
			)               # char or an 's' at a word ending position. This
							# is a special case to handle something like:
							# \"<i>Custer</i>'s Last Stand.\"
			}xi", '\1&#8217;', $_);
		
		# Any remaining single quotes should be opening ones:
		$_ = str_replace("'", '&#8216;', $_);
		
		# Get most opening double quotes:
		$_ = preg_replace("{
			(
				\\s          |   # a whitespace char, or
				&nbsp;      |   # a non-breaking space entity, or
				--          |   # dashes, or
				&[mn]dash;  |   # named dash entities
				$dec_dashes |   # or decimal entities
				&\\#x201[34];    # or hex
			)
			\"                   # the quote
			(?=\\w)              # followed by a word character
			}x", '\1&#8220;', $_);
		
		# Double closing quotes:
		$_ = preg_replace("{
			($close_class)?
			\"
			(?(1)|(?=\\s))   # If $1 captured, then do nothing;
							   # if not, then make sure the next char is whitespace.
			}x", '\1&#8221;', $_);
		
		# Any remaining quotes should be opening ones.
		$_ = str_replace('"', '&#8220;', $_);
		
		// ----------------------------------
		// Return:
		// ----------------------------------
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Educates backticks
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _educate_backticks($_)
	{
		
		#   Parameter:  String.
		#     Returns:  The string, with ``backticks'' -style double quotes
		#               translated into HTML curly quote entities.
		# 
		#   Example input:  ``Isn't this fun?''
		#   Example output: &#8220;Isn't this fun?&#8221;
		
		$_ = str_replace(array("``",       "''",),
						 array('&#8220;', '&#8221;'), $_);
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Educates single backticks
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _educate_single_backticks($_)
	{
		
		#
		#   Parameter:  String.
		#     Returns:  The string, with `backticks' -style single quotes
		#               translated into HTML curly quote entities.
		#
		#    Example input: `Isn't this fun?'
		#   Example output: &#8216;Isn&#8217;t this fun?&#8217;
		
		$_ = str_replace(array("`",       "'",),
						 array('&#8216;', '&#8217;'), $_);
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Educates single backticks
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _educate_dashes($_)
	{
		
		#   Parameter:  String.
		#     Returns:  The string, with each instance of "--" translated to
		#               an em-dash HTML entity.
		
		$_ = str_replace('--', '&#8212;', $_);
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Educates dashes oldschool
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _educate_dashes_oldschool($_)
	{
		
		#   Parameter:  String.
		#     Returns:  The string, with each instance of "--" translated to
		#               an en-dash HTML entity, and each "---" translated to
		#               an em-dash HTML entity.
		
		#                      em         en
		$_ = str_replace(array("---",     "--",),
						 array('&#8212;', '&#8211;'), $_);
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Educates dashes oldschool
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _educate_dashes_oldschool_inverted($_)
	{
		
		#   Parameter:  String.
		#     Returns:  The string, with each instance of "--" translated to
		#               an em-dash HTML entity, and each "---" translated to
		#               an en-dash HTML entity. Two reasons why: First, unlike the
		#               en- and em-dash syntax supported by
		#               EducateDashesOldSchool(), it's compatible with existing
		#               entries written before SmartyPants 1.1, back when "--" was
		#               only used for em-dashes.  Second, em-dashes are more
		#               common than en-dashes, and so it sort of makes sense that
		#               the shortcut should be shorter to type. (Thanks to Aaron
		#               Swartz for the idea.)
		
		#                      en         em
		$_ = str_replace(array("---",     "--",),
						 array('&#8211;', '&#8212;'), $_);
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Educates ellipses
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _educate_ellipses($_)
	{
		
		#   Parameter:  String.
		#     Returns:  The string, with each instance of "..." translated to
		#               an ellipsis HTML entity. Also converts the case where
		#               there are spaces between the dots.
		#
		#   Example input:  Huh...?
		#   Example output: Huh&#8230;?
		
		$_ = str_replace(array("...",     ". . .",), '&#8230;', $_);
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Stupefy entities
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _stupefy_entities($_)
	{
		
		#   Parameter:  String.
		#   Returns:    The string, with each SmartyPants HTML entity translated to
		#               its ASCII counterpart.
		# 
		#   Example input:  &#8220;Hello &#8212; world.&#8221;
		#   Example output: "Hello -- world."
		
		#                       en-dash    em-dash
		$_ = str_replace(array('&#8211;', '&#8212;'),
						 array('-',       '--'), $_);
		
		# Single quote         open       close
		$_ = str_replace(array('&#8216;', '&#8217;'), "'", $_);
		
		# Double quote         open       close
		$_ = str_replace(array('&#8220;', '&#8221;'), '"', $_);
		
		$_ = str_replace('&#8230;', '...', $_); # ellipsis
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Process escapes
	 * 
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	
	function _process_escapes($_)
	{
		
		#   Parameter:  String.
		#   Returns:    The string, with after processing the following backslash
		#               escape sequences. This is useful if you want to force a "dumb"
		#               quote or other character to appear.
		#
		#               Escape  Value
		#               ------  -----
		#               \\      &#92;
		#               \"      &#34;
		#               \'      &#39;
		#               \.      &#46;
		#               \-      &#45;
		#               \`      &#96;
		
		$_ = str_replace(
			array('\\',    '\"',    "\'",    '\.',    '\-',    '\`'),
			array('&#92;', '&#34;', '&#39;', '&#46;', '&#45;', '&#96;'), $_);
		
		return $_;
		
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Usage
	 *
	 * Plugin Usage
	 *
	 * @access	public
	 * @return	string
	 */
	
	function usage()
	{
		
		ob_start();
		
		?>
		
		ABOUT:
		
		Put whatever you want formatted with Smarty Pants between these tags in a template:
		
		{exp:smarty_pants}
			
			stuff...
			
		{/exp:smarty_pants}
		
		For information about Smarty Pants, examine the project page at daringfireball.com:
		
		http://daringfireball.net/projects/smartypants/
		
		ATTENTION:
		
		This is an unofficial release of Smarty Pants for Expression Engine 2.0.
		Use this code at your own risk.
		
		<?php
		
		$buffer = ob_get_contents();
		
		ob_end_clean(); 
		
		return $buffer;
		
	}
	
	// --------------------------------------------------------------------
	
}

/* End of file pi.smarty_pants.php */
/* Location: ./system/expressionengine/smarty_pants/pi.smarty_pants.php */