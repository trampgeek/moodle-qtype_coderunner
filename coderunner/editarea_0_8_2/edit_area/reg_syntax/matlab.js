// A very simplistic syntax definition for matlab. Ignores all the gazillions
// of matlab functions.
editAreaLoader.load_syntax["matlab"] = {
	'DISPLAY_NAME' : 'Matlab'
	,'COMMENT_SINGLE': { 1: '%'}
	, 'COMMENT_MULTI': {  }
	, 'QUOTEMARKS': { 1: "'" }
	, 'KEYWORD_CASE_SENSITIVE': true
	, 'KEYWORDS': {
		'statements': [
           'global', 'function', 'try', 'catch', 'end', 'break', 'case',
           'elseif', 'else', 'for', 'if', 'switch', 'otherwise', 'while'
		]
	}
	, 'OPERATORS': [
		'+', '-', '/', '*', '=', '<', '>', '!', '~', '^', '|', ':', '&', '\'', '.'
	]
	, 'DELIMITERS': [
		'(', ')', '[', ']', '{', '}'
	]

	, 'STYLES': {
	    'COMMENTS': 'color: #AAAAAA;'
		, 'QUOTESMARKS': 'color: #6381F8;'
		, 'KEYWORDS': {
		    'constants': 'color: #EE0000;'
			, 'types': 'color: #0000EE;'
			, 'statements': 'color: #60CA00;'
			, 'keywords': 'color: #48BDDF;'
		}
		, 'OPERATORS': 'color: #FF00FF;'
		, 'DELIMITERS': 'color: #0038E1;'
	}
};
