<?php
/*
 * xaseco flexitime plugin.
 *
 * Flexible time limit for tracks. The time remaining can be changed on the
 * fly, or queried, using the /timeleft chat command.
 * Copyright (c) 2015-2016 Tony Houghton ("realh")
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */

Aseco::registerEvent("onStartup", "realh_flexitime_startup");
Aseco::registerEvent("onBeginRound", "realh_flexitime_begin_round");
Aseco::registerEvent("onEndRound", "realh_flexitime_end_round");
Aseco::registerEvent("onEverySecond", "realh_flexitime_tick");

Aseco::addChatCommand("timeleft",
    "Change or query time left: /timeleft [[+|-]MINUTES]|[pause|resume]");

Aseco::addChatCommand("tl",
    "Quickly set remaining time to 5 minutes");

// You can comment out the next two lines if CUSTOM_TIME is false
Aseco::addChatCommand("timeset", "Sets custom timelimit in minutes for when " .
    "this track is played in future.");

global $realh_flexitime;

class FlexiTime {

    private $VERSION = "1.3.3";

    /* CONFIG OPTIONS, SET IN flexitime.xml */

    private $ADMIN_LEVEL = 1;

    private $CLOCK_COLOUR = "fff";

    private $WARN_TIME = 300;

    private $WARN_COLOUR = "ff4";

    private $DANGER_TIME = 60;

    private $DANGER_COLOUR = "f44";

    // Default time limit in minutes (maximum time if AUTHOR_MULT is not 0)
    private $DEFAULT_TIME = 90;

    // Whether to use custom time database and /timeset
    private $CUSTOM_TIME = true;

    // Default time for each track is AUTHOR_MULT x track's author time
    // or 0 to use $DEFAULT_TIME
    private $AUTHOR_MULT = 0;

    // Minimum time in minutes if using $AUTHOR_MULT
    private $MIN_TIME = 15;

    // Whether to send chat messages showing time left at regular intervals
    private $USE_CHAT = false;

    // Whether to show time in a panel
    private $SHOW_PANEL = true;

    // Only users with logins in this array can change time
    private $ADMINS = array("supercharn", "plext", "realh");

    /* End of CONFIG OPTIONS */

    private $aseco;
    private $time_left;
    private $author_time;
    private $paused;

    public function FlexiTime($aseco) {
        $this->aseco = $aseco;

        $xml = $aseco->xml_parser->parseXml("flexitime.xml");
        if ($xml && isset($xml['FLEXITIME'])) {
            $xml = $xml['FLEXITIME'];
            $this->ADMIN_LEVEL = $this->intFromXml($this->ADMIN_LEVEL, $xml, 'ADMIN_LEVEL');
            $admins = $this->fromXml(null, $xml, 'WHITELIST');
            if ($admins) {
                $admins = $admins['ADMIN'];
                if (is_array($admins))
                    $this->ADMINS = $admins;
            }
            $this->DEFAULT_TIME = $this->intFromXml($this->DEFAULT_TIME, $xml, 'DEFAULT_TIME');
            $this->CUSTOM_TIME = $this->boolFromXml($this->CUSTOM_TIME, $xml, 'CUSTOM_TIME');
            $this->AUTHOR_MULT = $this->intFromXml($this->AUTHOR_MULT, $xml, 'AUTHOR_MULT');
            $this->MIN_TIME = $this->intFromXml($this->MIN_TIME, $xml, 'MIN_TIME');
            $this->USE_CHAT = $this->boolFromXml($this->USE_CHAT, $xml, 'USE_CHAT');
            $this->SHOW_PANEL = $this->boolFromXml($this->SHOW_PANEL, $xml, 'SHOW_PANEL');
            $this->CLOCK_COLOUR = $this->fromXml($this->CLOCK_COLOUR, $xml, 'COLOUR');
            $this->WARN_TIME = $this->intFromXml($this->WARN_TIME, $xml, 'WARN_TIME');
            $this->WARN_COLOUR = $this->fromXml($this->WARN_COLOUR, $xml, 'WARN_COLOUR');
            $this->DANGER_TIME = $this->intFromXml($this->DANGER_TIME, $xml, 'DANGER_TIME');
            $this->DANGER_COLOUR = $this->fromXml($this->DANGER_COLOUR, $xml, 'DANGER_COLOUR');
        } else {
            print("flexitime.xml is missing or does not contain a <flexitime> tag\n");
        }

        /*
        print('$this->ADMIN_LEVEL ' . $this->ADMIN_LEVEL . "\n");
        print('$this->ADMINS ' . print_r($this->ADMINS, true) . "\n");
        print('$this->DEFAULT_TIME ' . $this->DEFAULT_TIME . "\n");
        print('$this->CUSTOM_TIME ' . $this->CUSTOM_TIME . "\n");
        print('$this->AUTHOR_MULT ' . $this->AUTHOR_MULT . "\n");
        print('$this->MIN_TIME ' . $this->MIN_TIME . "\n");
        print('$this->USE_CHAT ' . $this->USE_CHAT . "\n");
        print('$this->SHOW_PANEL ' . $this->SHOW_PANEL . "\n");
        print('$this->CLOCK_COLOUR ' . $this->CLOCK_COLOUR . "\n");
        print('$this->WARN_TIME ' . $this->WARN_TIME . "\n");
        print('$this->WARN_COLOUR ' . $this->WARN_COLOUR . "\n");
        print('$this->DANGER_TIME ' . $this->DANGER_TIME . "\n");
        print('$this->DANGER_COLOUR ' . $this->DANGER_COLOUR . "\n");
        */

        $this->initTimer();

        $this->showChatMsg('Started flexitime ' . $this->VERSION);
    }

    private function fromXml($default, $xml, $tag) {
        $v = $xml[$tag];
        if (isset($v) && isset($v[0])) {
            return $v[0];
        }
        /*
        if (isset($v)) {
            print($tag . " is set but doesn't contain [0]; it's: " .
                print_r($v, false) . "\n");
        } else {
            print("No " . $tag . " in xml\n");
        }
         */
        return $default;
    }

    private function intFromXml($default, $xml, $tag) {
        return intval($this->fromXml($default, $xml, $tag));
    }

    private function boolFromXml($default, $xml, $tag) {
        return $this->intFromXml($default, $xml, $tag) ? true : false;
    }

    public function initTimer() {
        $this->paused = false;

        $custom = $this->CUSTOM_TIME;
        $challenge = $this->getTrackInfo();
        $this->author_time = round($challenge->authortime / 1000);
        if ($custom) {
            $result = $this->arrayQuery(
                "SELECT tracktime FROM custom_tracktimes WHERE " .
                "challenge_uid='" .  $challenge->uid . "';");
            if (!empty($result)) {
                $timelimit = split(":", trim($result[0]['tracktime']));
                $this->time_left = $timelimit[0] * 60 + $timelimit[1];
            } else {
                $custom = false;
            }
        }
        if (!$custom) {
            if ($this->AUTHOR_MULT) {
                $this->time_left = ceil($challenge->authortime / 60000 *
                    $this->AUTHOR_MULT) * 60;
                if ($this->time_left > $this->DEFAULT_TIME * 60) {
                    $this->time_left = $this->DEFAULT_TIME * 60;
                } else if ($this->time_left < $this->MIN_TIME * 60) {
                    $this->time_left = $this->MIN_TIME * 60;
                }
            } else {
                $this->time_left = $this->DEFAULT_TIME * 60;
            }
        }
        $this->showPanel();
        if ($this->USE_CHAT)
            $this->showTimeLeftInChat();
    }

    public function commandTimeLeft($command, $emergency) {
        $param = trim($command["params"]);
        $login = $command["author"]->login;
        if (!$emergency && empty($param)) {
            $this->showPrivateMsg($login, $this->getTimeLeftText());
        } else {
            if ($this->authenticateCommand($command)) {
                if (!strcasecmp($param, "pause")) {
                    $this->paused = true;
                    $this->showChatMsg($login . " paused the timer.");
                    return;
                } else if (!strcasecmp($param, "resume")) {
                    $this->paused = false;
                    $this->showChatMsg($login . " unpaused the timer.");
                    return;
                }
                if ($emergency) {
                    $plus = false;
                    $minus = false;
                    $val = 300;
                } else {
                    $plus = ($param[0] == "+");
                    $minus = ($param[0] == "-");
                    $val = $param;
                    if ($plus || $minus) {
                        $val = substr($val, 1);
                    }
                    $val = intval($val);
                    if (!$val && !($param === "0")) {
                        $this->showPrivateMsg($login,
                            "Invalid parameter to /timeleft.");
                        return;
                    }
                    $val *= 60;
                }

                $tl = $this->timeleft;
                if ($plus) {
                    $tl += $val;
                } else if ($minus) {
                    $tl -= $val;
                } else {
                    $tl = $val;
                }
                if ($tl < 0) {
                    $this->showPrivateMsg($login,
                        "Can't set remaining time to less than zero.");
                }
                else
                {
                    $this->time_left = $tl;
                    $this->showPanel();
                    $this->showChatMsg($login . " changed time left: " .
                        $this->getTimeLeftText());
                    if ($this->time_left == 0) {
                        $this->nextRound();
                    }
                }
            }
        }
    }

    public function commandTimeSet($command) {
        // TODO: Allow (non-admin) users to query current value
        // if no param is given
        $login = $command["author"]->login;
        if (!$this->CUSTOM_TIME) {
            $this->showPrivateMsg($login,
                "/timeset command not enabled in plugin config.");
            return;
        }
        if (!$this->authenticateCommand($command)) {
            return;
        }
        $param = intval(trim($command["params"]));
        if (!$param) {
            $this->showPrivateMsg($login,
                "Usage (where 120 is number of minutes): /timeset 120");
            return;
        }
        $challenge = $this->getTrackInfo();
        $uid = $challenge->uid;
        // Would be better if challenge_uid was unique key, but want to be
        // backwards compatible with custom_time plugin's database
        $result = $this->arrayQuery(
            "SELECT * FROM custom_tracktimes WHERE challenge_uid = '" .
            $uid . "';");
        if (empty($result)) {
            mysql_query("INSERT INTO custom_tracktimes (challenge_uid, " .
                "tracktime) VALUES ('" .  $uid . "','" . $param . "');");
        } else {
            mysql_query("UPDATE custom_tracktimes SET tracktime='" . $param .
                "' WHERE challenge_uid='" . $uid . "';");
        }
        $this->showChatMsg($login . " set future time for this track to " .
            $param . " minutes.");
    }

    public function tick() {
        if (!$this->paused && $this->time_left > 0) {
            --$this->time_left;
        }
        $secs = $this->time_left;
        $mins = floor($secs / 60);
        $secs = $secs % 60;
        $this->showPanel();
        if ($USE_CHAT && !$this->paused && ((!$secs &&
                (!($mins % 10) || ($mins < 60 && !($mins % 5)) || $mins == 1))
            || (!$mins && ($secs == 30 || $secs == 10 || $secs == 0))))
        {
            $this->showTimeLeftInChat();
        }
        if (!$this->paused && $this->time_left <= 0) {
            $this->nextRound();
        }
    }

    private function authenticateCommand($command) {
        $user = $command["author"];
        $login = $user->login;
        if (in_array($login, $this->ADMINS) ||
            $this->ADMIN_LEVEL == 4 ||
            ($this->aseco->isMasterAdmin($user) && $this->ADMIN_LEVEL > 0) ||
            ($this->aseco->isAdmin($user) && $this->ADMIN_LEVEL > 1) ||
            ($this->aseco->isOperator($user) && $this->ADMIN_LEVEL > 2)) {
            return true;
        } else {
            $this->showPrivateMsg($login,
                "You do not have permission to change the remaining time.");
        }
        return false;
    }

    private function nextRound() {
        $this->paused = true;
        $this->aseco->client->query("NextChallenge");
    }

    private function getTimeLeftText() {
        $t = $this->getTimeLeftAsString();
        $suf = ($this->time_left >= 3600) ? " (h:m:s)" : " (m:s)";
        $status = $this->paused ? " (paused)." : ".";
        return $t . $suf . " until round end" . $status;
    }

    private function showTimeLeftInChat() {
        $this->showChatMsg($this->getTimeLeftText());
    }

    private function showChatMsg($msg) {
        $this->aseco->client->query("ChatSendServerMessage", "> " . $msg);
    }

    private function showPrivateMsg($login, $msg) {
        $this->aseco->client->query("ChatSendServerMessageToLogin",
            $msg, $login);
    }

    private function getTrackInfo() {
        $aseco = $this->aseco;
        $aseco->client->query('GetCurrentChallengeIndex');
        $trkid = $aseco->client->getResponse();
        $rtn = $aseco->client->query('GetChallengeList', 1, $trkid);
        $track = $aseco->client->getResponse();
        $rtn = $aseco->client->query('GetChallengeInfo', $track[0]['FileName']);
        $trackinfo = $aseco->client->getResponse();
        return new Challenge($trackinfo);
    }

    private function arrayQuery($query) 
    {
        $q = mysql_query($query);
        $error = mysql_error();
        if (strlen($error)) {
            print("Error with flexitime's MYSQL query! " . $error);
            return null;
        }
        while(true) {
            $row = mysql_fetch_assoc($q);
            if (!$row) {
                break;
            }
            $data[]=$row;
        }	
        mysql_free_result($q);
        return $data;
    }

    private function showHud($body) {
        if (!$this->SHOW_PANEL) {
            return;
        }
        // Arbitrary id = ('r' << 8) | 'h'
        $hud = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<manialink id="29288">' . $body . '</manialink>';
        $this->aseco->client->query("SendDisplayManialinkPage", $hud, 0, false);
    }

    private function showPanel() {
        if (!$this->SHOW_PANEL) {
            return;
        }
        $s = $this->time_left % 60;
        $m = floor($this->time_left / 60);
        $h = floor($m / 60);
        $m %= 60;
        if ($h) {
            $h = sprintf("%02d:", $h);
        } else {
            $h = "";
        }
        $colour = $this->CLOCK_COLOUR;
        if ($this->time_left < $this->DANGER_TIME)
            $colour = $this->DANGER_COLOUR;
        elseif ($this->time_left < $this->WARN_TIME ||
            $this->time_left < $this->author_time)
            $colour = $this->WARN_COLOUR;

        $showtime = $this->getTimeLeftAsString();
        $xpos = $this->paused ? "120" : "60";
        $this->showHud(
            '<frame scale="1" posn="' . $xpos . ' 20">' .
            '<quad posn="8 0 0" sizen="18 5 0.08" halign="right" ' .
            'valign="center" style="BgsPlayerCard" ' .
            'substyle="BgPlayerCardBig"/>' .
            '<label posn="3.5 0.1 0.1" halign="right" valign="center" ' .
            'scale="0.6"' .
            'style="TextRaceChrono" text="$s$' . $colour . $showtime . '"/>' .
            '</frame>');
    }

    private function getTimeLeftAsString() {
        $s = $this->time_left % 60;
        $m = floor($this->time_left / 60);
        $h = floor($m / 60);
        $m %= 60;
        if ($h) {
            $h = sprintf("%02d:", $h);
        } else {
            $h = "";
        }
        return $h . sprintf("%02d:%02d", $m, $s);
    }

    public function hidePanel() {
        $this->paused = true;
        $this->showHud("");
    }
}

function realh_flexitime_startup($aseco, $command) {
    global $realh_flexitime;
    $realh_flexitime = new FlexiTime($aseco);
}

function realh_flexitime_begin_round($aseco) {
    global $realh_flexitime;
    $realh_flexitime->initTimer();
}

function realh_flexitime_end_round($aseco) {
    global $realh_flexitime;
    $realh_flexitime->hidePanel();
}

function realh_flexitime_tick($aseco, $command) {
    global $realh_flexitime;
    $realh_flexitime->tick();
}

function chat_timeleft($aseco, $command) {
    global $realh_flexitime;
    $realh_flexitime->commandTimeLeft($command, false);
}

function chat_tl($aseco, $command) {
    global $realh_flexitime;
    $realh_flexitime->commandTimeLeft($command, true);
}

function chat_timeset($aseco, $command) {
    global $realh_flexitime;
    $realh_flexitime->commandTimeSet($command);
}
