<?

# Rebuild interwiki table using the file on meta and the language list
# Wikimedia specific!
$oldCwd = getcwd();

$optionsWithArgs = array( "o" );
include_once( "commandLine.inc" );

class Site {
	var $suffix, $lateral, $url;

	function Site( $s, $l, $u ) {
		$this->suffix = $s;
		$this->lateral = $l;
		$this->url = $u;
	}

	function getURL( $lang ) {
		return "http://$lang.{$this->url}/wiki/\$1";
	}
}

# Initialise lists of wikis
$sites = array( 
	'wiki' => new Site( 'wiki', 'w', 'wikipedia.org' ),
	'wiktionary' => new Site( 'wiktionary', 'wikt', 'wiktionary.org' )
);
$langlist = array_map( "trim", file( "/home/wikipedia/common/langlist" ) );

$specials = array( 
	'sourceswiki' => 'sources.wikipedia.org',
	'quotewiki' => 'wikiquote.org',
	'textbookwiki' => 'wikibooks.org',
	'sep11wiki' => 'sep11.wikipedia.org',
	'metawiki' => 'meta.wikipedia.org',
);

$extraLinks = array(
	array( 'm', 'http://meta.wikipedia.org/wiki/$1', 1 ),
	array( 'meta', 'http://meta.wikipedia.org/wiki/$1', 1 ),
	array( 'sep11', 'http://sep11.wikipedia.org/wiki/$1', 1 ),
);

$languageAliases = array(
	'zh-cn' => 'zh',
	'zh-tw' => 'zh',
);

# Extract the intermap from meta

$row = wfGetArray( "metawiki.cur", array( "cur_text" ), array( "cur_namespace" => 0, "cur_title" => "Interwiki_map" ) );

if ( !$row ) {
	die( "m:Interwiki_map not found" );
}

$lines = explode( "\n", $row->cur_text );
$iwArray = array();

foreach ( $lines as $line ) {
	if ( preg_match( '/^\|\s*(.*?)\s*\|\|\s*(.*?)\s*$/', $line, $matches ) ) {
		$prefix = $matches[1];
		$url = $matches[2];
		if ( preg_match( '/(wikipedia|wiktionary|wikisource|wikiquote|wikibooks)\.org/', $url ) ) {
			$local = 1;
		} else {
			$local = 0;
		}

		$iwArray[] = array( "iw_prefix" => $prefix, "iw_url" => $url, "iw_local" => $local );
	}
}


# Insert links into special wikis
# These have intermap links and interlanguage links pointing to wikipedia

$sql = "-- Generated by rebuildInterwiki.php";

foreach ( $specials as $db => $host ) {
	$sql .= "\nUSE $db;\n" .
			"TRUNCATE TABLE interwiki;\n" . 
			"INSERT INTO interwiki (iw_prefix, iw_url, iw_local) VALUES \n";
	$first = true;
	
	# Intermap links
	foreach ( $iwArray as $iwEntry ) {
		# Suppress links to self
		if ( strpos( $iwEntry['iw_url'], $host ) === false ) {
			$sql .= makeLink( $iwEntry, $first );
		}
	}
	# w link
	$sql .= makeLink( array("w", "http://en.wikipedia.org/wiki/$1", 1 ), $first );
	
	# Interlanguage links to wikipedia
	$sql .= makeLanguageLinks( $sites['wiki'], $first );

	# Extra links
	foreach ( $extraLinks as $link ) {
			$sql .= makeLink( $link, $first );
	}
	
	$sql .= ";\n";
}
$sql .= "\n";

# Insert links into multilanguage sites

foreach ( $sites as $site ) {
	$sql .= <<<EOS

---
--- {$site->suffix}
---

EOS;
	foreach ( $langlist as $lang ) {
		$db = $lang . $site->suffix;
		$db = str_replace( "-", "_", $db );

		$sql .= "USE $db;\n" .
				"TRUNCATE TABLE interwiki;\n" .
				"INSERT INTO interwiki (iw_prefix,iw_url,iw_local) VALUES\n";
		$first = true;

		# Intermap links
		foreach ( $iwArray as $iwEntry ) {
			# Suppress links to self
			if ( strpos( $iwEntry['iw_url'], $site->url ) === false || 
			  strpos( $iwEntry['iw_url'], 'meta.wikipedia.org' ) !== false ) {
				$sql .= makeLink( $iwEntry, $first );
			}
		}

		# Lateral links
		foreach ( $sites as $targetSite ) {
			# Suppress link to self
			if ( $targetSite->suffix != $site->suffix ) {
				$sql .= makeLink( array( $targetSite->lateral, $targetSite->getURL( $lang ), 1 ), $first );
			}
		}

		# Interlanguage links
		$sql .= makeLanguageLinks( $site, $first );

		# w link within wikipedias
		# Other sites already have it as a lateral link
		if ( $site->suffix == "wiki" ) {
			$sql .= makeLink( array("w", "http://en.wikipedia.org/wiki/$1", 1), $first );
		}
		
		# Extra links
		foreach ( $extraLinks as $link ){ 
				$sql .= makeLink( $link, $first );
		}
		$sql .= ";\n\n";
	}
}

# Output
if ( isset( $options['o'] ) ) {	
	# To file specified with -o
	chdir( $oldCwd );
	$file = fopen( $options['o'], "w" );
	fwrite( $file, $sql );
	fclose( $file );
} else {
	# To stdout
	print $sql;
}

# ------------------------------------------------------------------------------------------

# Returns part of an INSERT statement, corresponding to all interlanguage links to a particular site
function makeLanguageLinks( &$site, &$first ) {
	global $langlist, $languageAliases;

	$sql = "";

	# Actual languages with their own databases
	foreach ( $langlist as $targetLang ) {
		$sql .= makeLink( array( $targetLang, $site->getURL( $targetLang ), 1 ), $first );
	}

	# Language aliases
	foreach ( $languageAliases as $alias => $lang ) {
		$sql .= makeLink( array( $alias, $site->getURL( $lang ), 1 ), $first );
	}
	return $sql;
}

# Make SQL for a single link from an array
function makeLink( $entry, &$first ) {
	$sql = "";
	# Add comma
	if ( $first ) {
		$first = false;
	} else {
		$sql .= ",\n";
	}
	$sql .= "(" . Database::makeList( $entry ) . ")";
	return $sql;
}

?>
