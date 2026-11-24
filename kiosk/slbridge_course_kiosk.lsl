// SLBridge: Simulator Learning Bridge.
// Copyright (C) 2026 Nellie Deutsch.
// SPDX-License-Identifier: GPL-3.0-or-later.
//
// Independent course learning station kiosk.
// Supports Second Life and compatible OpenSimulator grids.
// Requires HMAC-SHA256 and outbound HTTPS.
// Keep this script private in an object you control.

string BASE_URL = "https://YOUR-MOODLE-SITE.example/local/slbridge";
string COURSE_URL = "https://YOUR-MOODLE-SITE.example/course/view.php?id=0";
string OBJECT_SECRET = "PASTE_OBJECT_CREDENTIAL_HERE";

integer channel;
integer activated = FALSE;

list requests = [];
list avatars = [];
list actions = [];
list deadlines = [];

list waiting = [];
list waitingActions = [];
list waitingExpiry = [];

string encode(string value) {
    return llEscapeURL(value);
}

string makeNonce() {
    return llDumpList2String(
        llParseString2List(
            llToLower((string)llGenerateKey()),
            ["-"],
            []
        ),
        ""
    );
}

string signature(
    string body,
    string timestamp,
    string nonce
) {
    string canonical = timestamp + "\n"
        + nonce + "\n"
        + llToLower((string)llGetKey())
        + "\n" + body;

    return llHMAC(
        OBJECT_SECRET,
        canonical,
        "sha256"
    );
}

integer credentialConfigured() {
    return llStringLength(OBJECT_SECRET) >= 32
        && OBJECT_SECRET != "PASTE_OBJECT_CREDENTIAL_HERE";
}

sendRequest(
    key avatar,
    string action,
    string endpoint,
    string body,
    integer signedRequest
) {
    if (llGetListLength(requests) >= 20) {
        llInstantMessage(
            avatar,
            "The kiosk is busy. Please try again."
        );
        return;
    }

    list headers = [
        HTTP_METHOD, "POST",
        HTTP_MIMETYPE,
        "application/x-www-form-urlencoded"
    ];

    if (signedRequest) {
        if (!credentialConfigured()) {
            llInstantMessage(
                avatar,
                "The kiosk credential has not been configured."
            );
            return;
        }

        string timestamp = (string)llGetUnixTime();
        string nonce = makeNonce();

        headers += [
            HTTP_CUSTOM_HEADER,
            "X-SLBridge-Object",
            llToLower((string)llGetKey()),

            HTTP_CUSTOM_HEADER,
            "X-SLBridge-Timestamp",
            timestamp,

            HTTP_CUSTOM_HEADER,
            "X-SLBridge-Nonce",
            nonce,

            HTTP_CUSTOM_HEADER,
            "X-SLBridge-Signature",
            signature(body, timestamp, nonce)
        ];
    }

    key request = llHTTPRequest(
        BASE_URL + endpoint,
        headers,
        body
    );

    if (request == NULL_KEY) {
        llInstantMessage(
            avatar,
            "SLBridge could not send the request."
        );
        return;
    }

    requests += [request];
    avatars += [avatar];
    actions += [action];
    deadlines += [llGetUnixTime() + 90];
}

removeRequest(integer position) {
    requests = llDeleteSubList(
        requests, position, position
    );

    avatars = llDeleteSubList(
        avatars, position, position
    );

    actions = llDeleteSubList(
        actions, position, position
    );

    deadlines = llDeleteSubList(
        deadlines, position, position
    );
}

removeWaiting(integer position) {
    waiting = llDeleteSubList(
        waiting, position, position
    );

    waitingActions = llDeleteSubList(
        waitingActions, position, position
    );

    waitingExpiry = llDeleteSubList(
        waitingExpiry, position, position
    );
}

askCode(
    key avatar,
    string action,
    string prompt
) {
    integer position = llListFindList(
        waiting,
        [avatar]
    );

    if (position != -1) {
        removeWaiting(position);
    }

    if (llGetListLength(waiting) >= 30) {
        llInstantMessage(
            avatar,
            "The kiosk is busy. Please try again."
        );
        return;
    }

    waiting += [avatar];
    waitingActions += [action];
    waitingExpiry += [llGetUnixTime() + 120];

    llTextBox(avatar, prompt, channel);
}

requestStatus(key avatar) {
    sendRequest(
        avatar,
        "status",
        "/api.php",
        "action=status&avatar="
            + encode((string)avatar),
        TRUE
    );
}

showMenu(key avatar, integer verified) {
    list buttons;

    if (verified) {
        buttons = [
            "Visit Station",
            "Open Course",
            "Link Status"
        ];
    } else {
        buttons = [
            "Link Avatar",
            "Open Course",
            "Link Status"
        ];
    }

    if (avatar == llGetOwner()) {
        buttons += ["Setup"];
    }

    llDialog(
        avatar,
        "SLBridge Learning Station",
        buttons,
        channel
    );
}

showSetup(key avatar) {
    if (avatar != llGetOwner()) return;

    llDialog(
        avatar,
        "SLBridge object registration and activation.",
        [
            "Pair Object",
            "Activate",
            "Link Status",
            "Open Course"
        ],
        channel
    );
}

default {
    state_entry() {
        channel = -100000
            - (integer)llFrand(100000000.0);

        llListen(channel, "", NULL_KEY, "");

        llSetText(
            "SLBridge Learning Station",
            <0.2, 0.8, 1.0>,
            1.0
        );

        llSetTimerEvent(15.0);
    }

    changed(integer change) {
        if (change & CHANGED_OWNER) {
            llResetScript();
        }
    }

    touch_start(integer count) {
        key avatar = llDetectedKey(0);

        if (!credentialConfigured()) {
            if (avatar == llGetOwner()) {
                showSetup(avatar);
            } else {
                llInstantMessage(
                    avatar,
                    "This learning station is awaiting setup."
                );
            }
            return;
        }

        if (avatar == llGetOwner()) {
            showSetup(avatar);
        } else {
            requestStatus(avatar);
        }
    }

    listen(
        integer ch,
        string name,
        key avatar,
        string message
    ) {
        if (ch != channel) return;

        if (message == "Setup") {
            showSetup(avatar);
            return;
        }

        if (message == "Pair Object") {
            if (avatar != llGetOwner()) return;

            askCode(
                avatar,
                "pair",
                "Enter the object pairing code generated in Moodle."
            );
            return;
        }

        if (message == "Activate") {
            if (avatar != llGetOwner()) return;

            sendRequest(
                avatar,
                "activate",
                "/objectapi/activate.php",
                "action=activate",
                TRUE
            );
            return;
        }

        if (message == "Open Course") {
            llLoadURL(
                avatar,
                "Open your Moodle course",
                COURSE_URL
            );
            return;
        }

        if (message == "Link Status") {
            requestStatus(avatar);
            return;
        }

        if (message == "Visit Station") {
            sendRequest(
                avatar,
                "visit",
                "/objectapi/index.php",
                "action=visit&avatar="
                    + llToLower((string)avatar),
                TRUE
            );
            return;
        }

        if (message == "Link Avatar") {
            askCode(
                avatar,
                "link",
                "Enter your 12-character Moodle avatar linking code."
            );
            return;
        }

        integer position = llListFindList(
            waiting,
            [avatar]
        );

        if (position == -1) return;

        string action = llList2String(
            waitingActions,
            position
        );

        integer expiry = llList2Integer(
            waitingExpiry,
            position
        );

        removeWaiting(position);

        if (llGetUnixTime() > expiry) {
            llInstantMessage(
                avatar,
                "The dialog expired. Please try again."
            );
            return;
        }

        message = llStringTrim(message, STRING_TRIM);

        if (action == "pair") {
            if (avatar != llGetOwner()) return;

            sendRequest(
                avatar,
                "pair",
                "/objectapi/claim.php",
                "code=" + encode(message),
                FALSE
            );
            return;
        }

        if (action == "link") {
            if (llStringLength(message) != 12) {
                llInstantMessage(
                    avatar,
                    "Invalid linking code."
                );
                return;
            }

            sendRequest(
                avatar,
                "link",
                "/api.php",
                "action=link&avatar="
                    + encode((string)avatar)
                    + "&name="
                    + encode(llKey2Name(avatar))
                    + "&code="
                    + encode(message),
                TRUE
            );
        }
    }

    http_response(
        key request,
        integer status,
        list metadata,
        string body
    ) {
        integer position = llListFindList(
            requests,
            [request]
        );

        if (position == -1) return;

        key avatar = llList2Key(
            avatars,
            position
        );

        string action = llList2String(
            actions,
            position
        );

        removeRequest(position);

        string result = llJsonGetValue(
            body,
            ["ok"]
        );

        string message = llJsonGetValue(
            body,
            ["message"]
        );

        if (status < 200 || status >= 300 || result != JSON_TRUE) {
            if (message == JSON_INVALID || message == JSON_NULL || message == "") {
                message = "Unable to complete the request.";
            }

            llInstantMessage(
                avatar,
                "SLBridge: " + llGetSubString(message, 0, 300)
                    + " (HTTP " + (string)status + ")"
            );
            return;
        }

        if (action == "status") {
            if (message == "AVATAR_VERIFIED") {
                showMenu(avatar, TRUE);
            } else if (message == "AVATAR_NOT_LINKED") {
                showMenu(avatar, FALSE);
            } else {
                llInstantMessage(
                    avatar,
                    "Unable to determine avatar verification status."
                );
            }
            return;
        }

        if (action == "activate") {
            activated = TRUE;

            llSetText(
                "SLBridge Learning Station\nReady",
                <0.2, 0.9, 0.4>,
                1.0
            );
        }

        if (action == "link") {
            llInstantMessage(
                avatar,
                "Return to Moodle and confirm your avatar."
            );
            return;
        }

        llInstantMessage(avatar, message);
    }

    timer() {
        integer now = llGetUnixTime();

        integer i = llGetListLength(requests) - 1;

        while (i >= 0) {
            if (llList2Integer(deadlines, i) < now) {
                removeRequest(i);
            }
            --i;
        }

        i = llGetListLength(waiting) - 1;

        while (i >= 0) {
            if (llList2Integer(waitingExpiry, i) < now) {
                removeWaiting(i);
            }
            --i;
        }
    }
}
