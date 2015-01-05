<?php
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['realurl'] = 'DmitryDulepov\Realurl\Encoder\UrlEncoder->encodeUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc']['realurl'] = 'DmitryDulepov\Realurl\Encoder\UrlEncoder->postProcessEncodedUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['realurl'] = 'DmitryDulepov\Realurl\Decoder\UrlDecoder->decodeUrl';
