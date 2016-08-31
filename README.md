Flexitime plugin for TrackMania Forever xaseco
==============================================

This plugin provides the `/timeleft` chat command, allowing players with the
appropriate privilege level to change the time left on the fly. It also
optionally provides functionality similar to the customtime (providing a
`/timeset` command) and authortime plugins.

Check [https://github.com/realh/flexitime] for updates.

To install: copy `plugin.flexitime.php` to xaseco's `plugins` directory and
`flexitime.xml` to the parent of that directory. Edit `flexitime.xml` (using a
decent editor with syntax highlighting is strongly recommened) to customise
the plugin for your server. The plugin runs its own timer in place of the
standard timer so disable the latter by setting `<timeattack_limit>` or
whatever to 0 in the server's `MatchSettings` config.

If you want to use the custom\_time (`/timeset`) feature you will need to make
sure your database has the appropriate table. See
[http://plugins.xaseco.org/info.php?id=13].

Example usage:

Query the time remaining:
    /timeleft

Set the remaining time to 10 minutes:
    /timeleft 10

Add 10 minutes to the remaining time:
    /timeleft +10

Subtract 10 minutes from the remaining time:
    /timeleft -10

Pause the countdown:
    /timeleft pause

Resume from pause:
    /timeleft resume

Copyright (c) 2015 Tony Houghton ("realh")

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to
deal in the Software without restriction, including without limitation the
rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
sell copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
IN THE SOFTWARE.
