<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.mp3.php                                        //
// module for analyzing MP3 files                              //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////


// number of frames to scan to determine if MPEG-audio sequence is valid
// Lower this number to 5-20 for faster scanning
// Increase this number to 50+ for most accurate detection of valid VBR/CBR
// mpeg-audio streams
define('GETID3_MP3_VALID_CHECK_FRAMES', 35);


class getid3_mp3 extends getid3_handler
{

	public $allow_bruteforce = false; // forces getID3() to scan the file byte-by-byte and log all the valid audio frame headers - extremely slow, unrecommended, but may provide data from otherwise-unusuable files

	public function Analyze() {
		$info = &$this->getid3->info;

		$initialOffset = $info['avdataoffset'];

		if (!$this->getOnlyMPEGaudioInfo($info['avdataoffset'])) {
			if ($this->allow_bruteforce) {
				$info['error'][] = 'Rescanning file in BruteForce mode';
				$this->getOnlyMPEGaudioInfoBruteForce($this->getid3->fp, $info);
			}
		}


		if (isset($info['mpeg']['audio']['bitrate_mode'])) {
			$info['audio']['bitrate_mode'] = strtolower($info['mpeg']['audio']['bitrate_mode']);
		}

		if (((isset($info['id3v2']['headerlength']) && ($info['avdataoffset'] > $info['id3v2']['headerlength'])) || (!isset($info['id3v2']) && ($info['avdataoffset'] > 0) && ($info['avdataoffset'] != $initialOffset)))) {

			$synchoffsetwarning = 'Unknown data before synch ';
			if (isset($info['id3v2']['headerlength'])) {
				$synchoffsetwarning .= '(ID3v2 header ends at '.$info['id3v2']['headerlength'].', then '.($info['avdataoffset'] - $info['id3v2']['headerlength']).' bytes garbage, ';
			} elseif ($initialOffset > 0) {
				$synchoffsetwarning .= '(should be at '.$initialOffset.', ';
			} else {
				$synchoffsetwarning .= '(should be at beginning of file, ';
			}
			$synchoffsetwarning .= 'synch detected at '.$info['avdataoffset'].')';
			if (isset($info['audio']['bitrate_mode']) && ($info['audio']['bitrate_mode'] == 'cbr')) {

				if (!empty($info['id3v2']['headerlength']) && (($info['avdataoffset'] - $info['id3v2']['headerlength']) == $info['mpeg']['audio']['framelength'])) {

					$synchoffsetwarning .= '. This is a known problem with some versions of LAME (3.90-3.92) DLL in CBR mode.';
					$info['audio']['codec'] = 'LAME';
					$CurrentDataLAMEversionString = 'LAME3.';

				} elseif (empty($info['id3v2']['headerlength']) && ($info['avdataoffset'] == $info['mpeg']['audio']['framelength'])) {

					$synchoffsetwarning .= '. This is a known problem with some versions of LAME (3.90 - 3.92) DLL in CBR mode.';
					$info['audio']['codec'] = 'LAME';
					$CurrentDataLAMEversionString = 'LAME3.';

				}

			}
			$info['warning'][] = $synchoffsetwarning;

		}

		if (isset($info['mpeg']['audio']['LAME'])) {
			$info['audio']['codec'] = 'LAME';
			if (!empty($info['mpeg']['audio']['LAME']['long_version'])) {
				$info['audio']['encoder'] = rtrim($info['mpeg']['audio']['LAME']['long_version'], "\x00");
			} elseif (!empty($info['mpeg']['audio']['LAME']['short_version'])) {
				$info['audio']['encoder'] = rtrim($info['mpeg']['audio']['LAME']['short_version'], "\x00");
			}
		}

		$CurrentDataLAMEversionString = (!empty($CurrentDataLAMEversionString) ? $CurrentDataLAMEversionString : (isset($info['audio']['encoder']) ? $info['audio']['encoder'] : ''));
		if (!empty($CurrentDataLAMEversionString) && (substr($CurrentDataLAMEversionString, 0, 6) == 'LAME3.') && !preg_match('[0-9\)]', substr($CurrentDataLAMEversionString, -1))) {
			// a version number of LAME that does not end with a number like "LAME3.92"
			// or with a closing parenthesis like "LAME3.88 (alpha)"
			// or a version of LAME with the LAMEtag-not-filled-in-DLL-mode bug (3.90-3.92)

			// not sure what the actual last frame length will be, but will be less than or equal to 1441
			$PossiblyLongerLAMEversion_FrameLength = 1441;

			// Not sure what version of LAME this is - look in padding of last frame for longer version string
			$PossibleLAMEversionStringOffset = $info['avdataend'] - $PossiblyLongerLAMEversion_FrameLength;
			$this->fseek($PossibleLAMEversionStringOffset);
			$PossiblyLongerLAMEversion_Data = $this->fread($PossiblyLongerLAMEversion_FrameLength);
			switch (substr($CurrentDataLAMEversionString, -1)) {
				case 'a':
				case 'b':
					// "LAME3.94a" will have a longer version string of "LAME3.94 (alpha)" for example
					// need to trim off "a" to match longer string
					$CurrentDataLAMEversionString = substr($CurrentDataLAMEversionString, 0, -1);
					break;
			}
			if (($PossiblyLongerLAMEversion_String = strstr($PossiblyLongerLAMEversion_Data, $CurrentDataLAMEversionString)) !== false) {
				if (substr($PossiblyLongerLAMEversion_String, 0, strlen($CurrentDataLAMEversionString)) == $CurrentDataLAMEversionString) {
					$PossiblyLongerLAMEversion_NewString = substr($PossiblyLongerLAMEversion_String, 0, strspn($PossiblyLongerLAMEversion_String, 'LAME0123456789., (abcdefghijklmnopqrstuvwxyzJFSOND)')); //"LAME3.90.3"  "LAME3.87 (beta 1, Sep 27 2000)" "LAME3.88 (beta)"
					if (empty($info['audio']['encoder']) || (strlen($PossiblyLongerLAMEversion_NewString) > strlen($info['audio']['encoder']))) {
						$info['audio']['encoder'] = $PossiblyLongerLAMEversion_NewString;
					}
				}
			}
		}
		if (!empty($info['audio']['encoder'])) {
			$info['audio']['encoder'] = rtrim($info['audio']['encoder'], "\x00 ");
		}

		switch (isset($info['mpeg']['audio']['layer']) ? $info['mpeg']['audio']['layer'] : '') {
			case 1:
			case 2:
				$info['audio']['dataformat'] = 'mp'.$info['mpeg']['audio']['layer'];
				break;
		}
		if (isset($info['fileformat']) && ($info['fileformat'] == 'mp3')) {
			switch ($info['audio']['dataformat']) {
				case 'mp1':
				case 'mp2':
				case 'mp3':
					$info['fileformat'] = $info['audio']['dataformat'];
					break;

				default:
					$info['warning'][] = 'Expecting [audio][dataformat] to be mp1/mp2/mp3 when fileformat == mp3, [audio][dataformat] actually "'.$info['audio']['dataformat'].'"';
					break;
			}
		}

		if (empty($info['fileformat'])) {
			unset($info['fileformat']);
			unset($info['audio']['bitrate_mode']);
			unset($info['avdataoffset']);
			unset($info['avdataend']);
			return false;
		}

		$info['mime_type']         = 'audio/mpeg';
		$info['audio']['lossless'] = false;

		// Calculate playtime
		if (!isset($info['playtime_seconds']) && isset($info['audio']['bitrate']) && ($info['audio']['bitrate'] > 0)) {
			$info['playtime_seconds'] = ($info['avdataend'] - $info['avdataoffset']) * 8 / $info['audio']['bitrate'];
		}

		$info['audio']['encoder_options'] = $this->GuessEncoderOptions();

		return true;
	}


	public function GuessEncoderOptions() {
		// shortcuts
		$info = &$this->getid3->info;
		if (!empty($info['mpeg']['audio'])) {
			$thisfile_mpeg_audio = &$info['mpeg']['audio'];
			if (!empty($thisfile_mpeg_audio['LAME'])) {
				$thisfile_mpeg_audio_lame = &$thisfile_mpeg_audio['LAME'];
			}
		}

		$encoder_options = '';
		static $NamedPresetBitrates = array(16, 24, 40, 56, 112, 128, 160, 192, 256);

		if (isset($thisfile_mpeg_audio['VBR_method']) && ($thisfile_mpeg_audio['VBR_method'] == 'Fraunhofer') && !empty($thisfile_mpeg_audio['VBR_quality'])) {

			$encoder_options = 'VBR q'.$thisfile_mpeg_audio['VBR_quality'];

		} elseif (!empty($thisfile_mpeg_audio_lame['preset_used']) && (!in_array($thisfile_mpeg_audio_lame['preset_used_id'], $NamedPresetBitrates))) {

			$encoder_options = $thisfile_mpeg_audio_lame['preset_used'];

		} elseif (!empty($thisfile_mpeg_audio_lame['vbr_quality'])) {

			static $KnownEncoderValues = array();
			if (empty($KnownEncoderValues)) {

				//$KnownEncoderValues[abrbitrate_minbitrate][vbr_quality][raw_vbr_method][raw_noise_shaping][raw_stereo_mode][ath_type][lowpass_frequency] = 'preset name';
				$KnownEncoderValues[0xFF][58][1][1][3][2][20500] = '--alt-preset insane';        // 3.90,   3.90.1, 3.92
				$KnownEncoderValues[0xFF][58][1][1][3][2][20600] = '--alt-preset insane';        // 3.90.2, 3.90.3, 3.91
				$KnownEncoderValues[0xFF][57][1][1][3][4][20500] = '--alt-preset insane';        // 3.94,   3.95
				$KnownEncoderValues['**'][78][3][2][3][2][19500] = '--alt-preset extreme';       // 3.90,   3.90.1, 3.92
				$KnownEncoderValues['**'][78][3][2][3][2][19600] = '--alt-preset extreme';       // 3.90.2, 3.91
				$KnownEncoderValues['**'][78][3][1][3][2][19600] = '--alt-preset extreme';       // 3.90.3
				$KnownEncoderValues['**'][78][4][2][3][2][19500] = '--alt-preset fast extreme';  // 3.90,   3.90.1, 3.92
				$KnownEncoderValues['**'][78][4][2][3][2][19600] = '--alt-preset fast extreme';  // 3.90.2, 3.90.3, 3.91
				$KnownEncoderValues['**'][78][3][2][3][4][19000] = '--alt-preset standard';      // 3.90,   3.90.1, 3.90.2, 3.91, 3.92
				$KnownEncoderValues['**'][78][3][1][3][4][19000] = '--alt-preset standard';      // 3.90.3
				$KnownEncoderValues['**'][78][4][2][3][4][19000] = '--alt-preset fast standard'; // 3.90,   3.90.1, 3.90.2, 3.91, 3.92
				$KnownEncoderValues['**'][78][4][1][3][4][19000] = '--alt-preset fast standard'; // 3.90.3
				$KnownEncoderValues['**'][88][4][1][3][3][19500] = '--r3mix';                    // 3.90,   3.90.1, 3.92
				$KnownEncoderValues['**'][88][4][1][3][3][19600] = '--r3mix';                    // 3.90.2, 3.90.3, 3.91
				$KnownEncoderValues['**'][67][4][1][3][4][18000] = '--r3mix';                    // 3.94,   3.95
				$KnownEncoderValues['**'][68][3][2][3][4][18000] = '--alt-preset medium';        // 3.90.3
				$KnownEncoderValues['**'][68][4][2][3][4][18000] = '--alt-preset fast medium';   // 3.90.3

				$KnownEncoderValues[0xFF][99][1][1][1][2][0]     = '--preset studio';            // 3.90,   3.90.1, 3.90.2, 3.91, 3.92
				$KnownEncoderValues[0xFF][58][2][1][3][2][20600] = '--preset studio';            // 3.90.3, 3.93.1
				$KnownEncoderValues[0xFF][58][2][1][3][2][20500] = '--preset studio';            // 3.93
				$KnownEncoderValues[0xFF][57][2][1][3][4][20500] = '--preset studio';            // 3.94,   3.95
				$KnownEncoderValues[0xC0][88][1][1][1][2][0]     = '--preset cd';                // 3.90,   3.90.1, 3.90.2,   3.91, 3.92
				$KnownEncoderValues[0xC0][58][2][2][3][2][19600] = '--preset cd';                // 3.90.3, 3.93.1
				$KnownEncoderValues[0xC0][58][2][2][3][2][19500] = '--preset cd';                // 3.93
				$KnownEncoderValues[0xC0][57][2][1][3][4][19500] = '--preset cd';                // 3.94,   3.95
				$KnownEncoderValues[0xA0][78][1][1][3][2][18000] = '--preset hifi';              // 3.90,   3.90.1, 3.90.2,   3.91, 3.92
				$KnownEncoderValues[0xA0][58][2][2][3][2][18000] = '--preset hifi';              // 3.90.3, 3.93,   3.93.1
				$KnownEncoderValues[0xA0][57][2][1][3][4][18000] = '--preset hifi';              // 3.94,   3.95
				$KnownEncoderValues[0x80][67][1][1][3][2][18000] = '--preset tape';              // 3.90,   3.90.1, 3.90.2,   3.91, 3.92
				$KnownEncoderValues[0x80][67][1][1][3][2][15000] = '--preset radio';             // 3.90,   3.90.1, 3.90.2,   3.91, 3.92
				$KnownEncoderValues[0x70][67][1][1][3][2][15000] = '--preset fm';                // 3.90,   3.90.1, 3.90.2,   3.91, 3.92
				$KnownEncoderValues[0x70][58][2][2][3][2][16000] = '--preset tape/radio/fm';     // 3.90.3, 3.93,   3.93.1
				$KnownEncoderValues[0x70][57][2][1][3][4][16000] = '--preset tape/radio/fm';     // 3.94,   3.95
				$KnownEncoderValues[0x38][58][2][2][0][2][10000] = '--preset voice';             // 3.90.3, 3.93,   3.93.1
				$KnownEncoderValues[0x38][57][2][1][0][4][15000] = '--preset voice';             // 3.94,   3.95
				$KnownEncoderValues[0x38][57][2][1][0][4][16000] = '--preset voice';             // 3.94a14
				$KnownEncoderValues[0x2