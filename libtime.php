<?php

class libTime {

    public function getTimezoneNameBy($offset) {
        foreach (DateTimeZone::listAbbreviations() as $group) {
            foreach ($group as $timezone) {
                if ($timezone['offset'] === $offset && !$timezone['dst']) {
                    return $timezone['timezone_id'];
                }
            }
        }
    }


    public function getTimezoneNameByRaw($timezone) {
        $arrTzone = explode(':', $timezone);
        $diffHour = ((int) $arrTzone[0]) * 60 * 60;
        $diffMin  = ((int) $arrTzone[1]) * 60;
        $timeDiff =  $diffHour > 0
                  ? ($diffHour + $diffMin)
                  : ($diffHour - $diffMin);
        return $this->getTimezoneNameBy($timeDiff);
    }


    public function getDigitalTimezoneBy($timezoneName) {
        $timezoneName = strtolower(trim($timezoneName));
        $timezoneDiff = null;
        foreach (DateTimeZone::listAbbreviations() as $group) {
            if ($timezoneDiff !== null) {
                break;
            }
            foreach ($group as $timezone) {
                if (strtolower(trim($timezone['timezone_id'])) === $timezoneName
                && !$timezone['dst']) {
                    $timezoneDiff = $timezone['offset'];
                    break;
                }
            }
        }
        if ($timezoneDiff === null) {
            return '';
        } else {
            return $this->getDigitalTimezoneByRaw($timezoneDiff);
        }
    }


    public function getDigitalTimezoneByRaw($offset) {
        return $this->convertFacebookTimezone($offset / 60 / 60);
    }


    public function convertFacebookTimezone($num) {
        // http://stackoverflow.com/questions/9204818/how-to-convert-facebook-time-zone-to-a-custom-string
        $res = ($num < 0 ? '-' : '+') . (abs($num) < 10 ? '0' : '') . abs((int)$num) . ':';
        // --> -05:
        $mins = round((abs($num) - abs((int)$num)) * 60);
        // --> 30
        $res .= ($mins < 10 ? '0' : '') . $mins;
        return $res;
    }


    public function parseTimeString($origin_string, $timezone = '') {
        // check timezone
        if (preg_match('/^[+-][0-9]{2}:[0-9]{2}(\ [a-z]{1,5})?$/i', $timezone)) {
        } else if (preg_match('/^[a-z]{1,5}\ ([+-][0-9]{2}:[0-9]{2})$/i', $timezone)) {
            $timezone = preg_replace('/^[a-z]{1,5}\ ([+-][0-9]{2}:[0-9]{2})$/i', '$1', $timezone);
        } else if (strlen($origin_string) > 0) {
            return 'timezone_error';
        } else {
            return ['', '', '', '', '', '', 0];
        }
        switch ($timezone) {
            case 'z':
            case 'utc':
            case 'gmt':
                $timezone = '+00:00 GMT';
        }
        $arrTzone = explode(':', $timezone);
        $diffHour = ((int) $arrTzone[0]) * 60 * 60;
        $diffMin  = ((int) $arrTzone[1]) * 60;
        $timeDiff =  $diffHour > 0
                  ? ($diffHour + $diffMin)
                  : ($diffHour - $diffMin);
        $timezoneName = $this->getTimezoneNameBy($timeDiff);
        @date_default_timezone_set($timezoneName);
        // init
        $date_word    = '';
        $date         = '';
        $time_word    = '';
        $time         = '';
        $outputformat = 0;
        $intDayPlus   = 0;
        $string       = $origin_string;
        // special time
        $specialTimeDic = [
            [
                'patterns' => [
                    '/(the )?apocalypse/i',
                    '/(the )?last day of (the )?world/i',
                    '/(the )?doomsday/i',
                    '/(the )?end of (the )?world/i',
                    '/(the )?last judgement/i',
                    '/(the )?judgment day/i',
                    '/(the )?crack of doom/i',
                    '/(the )?end of (the )?(day(s)?|world)/i',
                    '/(the )?doom day/i',
                    '/(the )?day of doom/i',
                    '/(the )?day of (the )?last judgment/i',
                ],
                'date'     => '2012-12-21',
                'time'     => '',
            ],
        ];
        foreach ($specialTimeDic as $specialTime) {
            $found = false;
            foreach ($specialTime['patterns'] as $patterns) {
                if (preg_match($patterns, $string)) {
                    if ($specialTime['date']) {
                        $date = $specialTime['date'];
                    }
                    if ($specialTime['time']) {
                        $time = $specialTime['time'];
                    }
                    $found = true;
                    break;
                }
            }
            if ($found) {
                break;
            }
        }
        // fix time string
        if (preg_match('/^[0-9]{1,4}\.[0-9]{1,4}\.[0-9]{1,4}$/', $string)) {
            $string = preg_replace('/\./', '-', $string);
        }
        if (preg_match('/^\".*\"$|^\'.*\'$/', $string)) {
            return [
                $date_word, $date, $time_word, $time, $timezone, $string, 1
            ];
        }
        $untreated    = trim($string, '"\'');
        $dtUntreated  = trim($string, '"\'');
        // get fuzzy time
        $fuzzyTimeDic = [
            ['Daybreak'],
            ['Dawn'],
            ['Breakfast'],
            ['Morning'],
            ['Brunch'],
            ['Lunch'],
            ['Noon'],
            ['Afternoon'],
            ['Tea-break', 'tea break', 'teabreak', 'Tea-time', 'tea time', 'teatime'],
            ['Coffee-break', 'coffee break'],
            ['Off-work',  'off work',  'offwork'],
            ['Dinner'],
            ['Evening'],
            ['Midnight'],
            ['Late-night', 'Late night'],
            ['Night'],
        ];
        $fuzzyTime = [];
        foreach ($fuzzyTimeDic as $fuzzyWord) {
            foreach ($fuzzyWord as $fuzzyWordItem) {
                $pattern = "/^.*(\b{$fuzzyWordItem}\b).*$/i";
                if (preg_match($pattern, $untreated)) {
                    $fuzzyTime[] = $fuzzyWord[0];
                    $rawTime     = preg_replace($pattern, '$1', $untreated);
                    $untreated   = str_replace($rawTime, '', $untreated);
                    $dtUntreated = str_replace($rawTime, '', $dtUntreated);
                }
            }
        }
        if ($fuzzyTime) {
            $time_word = $fuzzyTime[0];
        }
        // get raw date
        $pattern = '/^.*([0-9]{4}\-[0-9]{1,4}\-[0-9]{1,4}).*$/';
        if (preg_match($pattern, $untreated)
         && ($rawDStr = preg_replace($pattern, '$1', $untreated))
         && ($rawDate = strtotime($rawDStr)) !== false) {
            $date = date('Y-m-d', $rawDate);
            $untreated = str_replace($rawDStr, '', $untreated);
        } else if (($rawDate = strtotime($untreated)) && $rawDate !== false) {
            $date = date('Y-m-d', $rawDate);
            $year = date('Y', $rawDate);
            if (mb_substr_count($untreated, $year, 'utf8') === 1) {
                $untreated = str_replace($year, '', $untreated);
            }
        }
        // get precise time
        $timePatterns = [
            '/^.*[^\/\\\-]*(\b[0-9]{1,4}\ ?[ap]\.?m\.?\b).*$/i',
            '/^.*[^\/\\\-]*(\b[0-9]{3,4}\ ?([ap]\.?m\.?)?\b).*$/i',
            '/^.*[^\/\\\-]*(\b[0-9]{1,2}\:[0-9]{1,2}\ ?([ap]\.?m\.?)?\b).*$/i',
            '/^.*[^\/\\\-]*(\b[0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ ?([ap]\.?m\.?)?\b).*$/i',
        ];
        $actTimes     = [];
        do {
            $lenTaken = 0;
            $rawTime  = '';
            foreach ($timePatterns as $pattern) {
                if (preg_match($pattern, $untreated)) {
                    $tryTime  = preg_replace($pattern, '$1', $untreated);
                    $tryTaken = mb_strlen($tryTime, 'utf8');
                    if ($tryTaken >= $lenTaken) {
                        $rawTime   = $tryTime;
                        $lenTaken  = $tryTaken;
                    }
                }
            }
            if ($rawTime) {
                // get digitals
                if (preg_match('/\:/', $rawTime)) {
                    $arrATime = explode(':', $rawTime);
                    $rawHour  = $arrATime[0];
                    $rawMin   = $arrATime[1];
                    $rawSec   = sizeof($arrATime) > 2 ? $arrATime[2] : 0;
                } else {
                    $dgts = preg_replace('/^[^0-9]*([0-9]*)[^0-9]*$/', '$1', $rawTime);
                    switch (($lenDgts = mb_strlen($dgts, 'utf8'))) {
                        case 1:
                        case 2:
                            $rawHour = $dgts;
                            $rawMin  = 0;
                            break;
                        case 3:
                        case 4:
                            $rawHour = mb_substr($dgts, 0, $lenDgts - 2, 'utf8');
                            $rawMin  = mb_substr($dgts, $lenDgts - 2, 2, 'utf8');
                    }
                    $rawSec = 0;
                }
                $rawHour = (int) $rawHour;
                $rawMin  = (int) $rawMin;
                $rawSec  = (int) $rawSec;
                // get am/pm
                if (preg_match('/a\.?m\.?/i', $rawTime)) {
                    $apm = 'am';
                } else if (preg_match('/p\.?m\.?/i', $rawTime)) {
                    $apm = 'pm';
                } else {
                    $apm = $rawHour < 12 ? 'am' : 'pm';
                }
                // merge
                switch ($apm) {
                    case 'pm':
                        if ($rawHour <  12) {
                            $rawHour += 12;
                        }
                        break;
                    case 'am':
                    default:
                        if ($rawHour === 12) {
                            $rawHour  =  0;
                        }
                }
                $rawMin     += (int) ($rawSec  / 60);
                $rawSec      = $rawSec  % 60;
                $rawHour    += (int) ($rawMin  / 60);
                $rawMin      = $rawMin  % 60;
                $intDayPlus += (int) ($rawHour / 24);
                $rawHour     = $rawHour % 24;
                $actTimes[]  = [
                    'raw'  => $rawTime,
                    'hour' => $rawHour,
                    'min'  => $rawMin,
                    'sec'  => $rawSec,
                ];
                $untreated   = str_replace($rawTime, '', $untreated);
                $dtUntreated = str_replace($rawTime, '', $dtUntreated);
            }
        } while ($rawTime);
        if ($actTimes) {
            $time = sprintf('%02d', $actTimes[0]['hour']) . ':'
                  . sprintf('%02d', $actTimes[0]['min'])  . ':'
                  . sprintf('%02d', $actTimes[0]['sec']);
        }
        // get date
        $rawDate  = strtotime($dtUntreated);
        if (!$date && $rawDate !== false) {
            $rawDate += $intDayPlus * 60 * 60 * 24;
            $date = date('Y-m-d', $rawDate);
        }
        // make returns
        if ((sizeof($actTimes) && sizeof($fuzzyTime)) || sizeof($fuzzyTime) > 1
         || (!$date_word && !$date && !$time_word && !$time)) {
            $outputformat = 1;
        }
        // fix timezone
        if ($date && sizeof($actTimes)) {
            $intDate  = strtotime("{$date} {$time}");
            $fixTime  = explode(' ', date('Y-m-d H:i:s', $intDate - $timeDiff));
            $date     = $fixTime[0];
            $time     = $fixTime[1];
        }
        // return
        @date_default_timezone_set('UTC');
        return [$date_word, $date, $time_word, $time, $timezone, $origin_string, $outputformat];
    }

}
