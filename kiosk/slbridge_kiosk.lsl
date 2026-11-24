// SLBridge persistent avatar kiosk.
// Moodle remains the source of truth for avatar verification.
// Keep this script private in an object you control.

string API_URL = "https://YOUR-MOODLE-SITE.example/local/slbridge/api.php";
string API_KEY = "PASTE_YOUR_GENERATED_API_KEY_HERE";
string COURSE_URL = "https://YOUR-MOODLE-SITE.example/course/view.php?id=0";
integer SESSION_ID = 0;

integer channel;
list requests = [];
list avatars = [];
list requestTypes = [];
list waitingForCode = [];
list codeExpiry = [];

string encode(string value) {
    return llEscapeURL(value);
}

string signature(string body, string timestamp, string nonce) {
    string canonical = timestamp + "\n" + nonce + "\n"
        + llToLower((string)llGetKey()) + "\n" + body;

    return llHMAC(API_KEY, canonical, "sha256");
}

sendRequest(key avatar, string action, string body) {
    string timestamp = (string)llGetUnixTime();
    string nonce = (string)llGenerateKey();

    key request = llHTTPRequest(API_URL, [
        HTTP_METHOD, "POST",
        HTTP_MIMETYPE, "application/x-www-form-urlencoded",
        HTTP_CUSTOM_HEADER, "X-SLBridge-Object", (string)llGetKey(),
        HTTP_CUSTOM_HEADER, "X-SLBridge-Timestamp", timestamp,
        HTTP_CUSTOM_HEADER, "X-SLBridge-Nonce", nonce,
        HTTP_CUSTOM_HEADER, "X-SLBridge-Signature",
            signature(body, timestamp, nonce)
    ], body);

    if (request == NULL_KEY) {
        llInstantMessage(avatar, "SLBridge could not send its request.");
        return;
    }

    requests += [request];
    avatars += [avatar];
    requestTypes += [action];
}

showMenu(key avatar, integer verified) {
    if (verified) {
        llDialog(
            avatar,
            "Your Moodle avatar is linked. Choose an option.",
            ["Open Course", "Attendance", "Link Status"],
            channel
        );
    } else {
        llDialog(
            avatar,
            "Link your Moodle avatar once to access SLBridge.",
            ["Link Avatar", "Open Course", "Link Status"],
            channel
        );
    }
}

requestStatus(key avatar) {
    sendRequest(
        avatar,
        "status",
        "action=status&avatar=" + encode((string)avatar)
    );
}

default {
    state_entry() {
        channel = -100000 - (integer)llFrand(100000000.0);
        llListen(channel, "", NULL_KEY, "");

        llSetText(
            "SLBridge: Moodle Learning Kiosk",
            <0.2, 0.8, 1.0>,
            1.0
        );
    }

    touch_start(integer count) {
        key avatar = llDetectedKey(0);
        requestStatus(avatar);
    }

    listen(integer ch, string name, key avatar, string message) {
        if (ch != channel) return;

        if (message == "Link Status") {
            requestStatus(avatar);
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

        if (message == "Attendance") {
            if (SESSION_ID <= 0) {
                llInstantMessage(
                    avatar,
                    "No attendance session is configured."
                );
                return;
            }

            sendRequest(
                avatar,
                "attendance",
                "action=attendance&avatar="
                + encode((string)avatar)
                + "&session=" + (string)SESSION_ID
                + "&region=" + encode(llGetRegionName())
            );
            return;
        }

        if (message == "Link Avatar") {
            integer old = llListFindList(waitingForCode, [avatar]);

            if (old != -1) {
                waitingForCode = llDeleteSubList(
                    waitingForCode, old, old
                );
                codeExpiry = llDeleteSubList(
                    codeExpiry, old, old
                );
            }

            waitingForCode += [avatar];
            codeExpiry += [llGetUnixTime() + 120];

            llTextBox(
                avatar,
                "Enter your one-time 12-character Moodle linking code.",
                channel
            );
            return;
        }

        if (llStringLength(message) != 12) return;

        integer position = llListFindList(waitingForCode, [avatar]);

        if (position == -1) return;

        integer expiry = llList2Integer(codeExpiry, position);

        waitingForCode = llDeleteSubList(
            waitingForCode, position, position
        );
        codeExpiry = llDeleteSubList(
            codeExpiry, position, position
        );

        if (llGetUnixTime() > expiry) {
            llInstantMessage(
                avatar,
                "The linking dialog expired. Touch the kiosk again."
            );
            return;
        }

        sendRequest(
            avatar,
            "link",
            "action=link&avatar=" + encode((string)avatar)
            + "&name=" + encode(llKey2Name(avatar))
            + "&code=" + encode(message)
        );
    }

    http_response(
        key request,
        integer status,
        list metadata,
        string body
    ) {
        integer index = llListFindList(requests, [request]);
        if (index == -1) return;

        key avatar = llList2Key(avatars, index);
        string action = llList2String(requestTypes, index);

        requests = llDeleteSubList(requests, index, index);
        avatars = llDeleteSubList(avatars, index, index);
        requestTypes = llDeleteSubList(requestTypes, index, index);

        if (status != 200) {
            llInstantMessage(
                avatar,
                "SLBridge connection failed (HTTP "
                + (string)status + "). "
                + llGetSubString(body, 0, 180)
            );
            return;
        }

        if (action == "status") {
            if (llSubStringIndex(body, "AVATAR_VERIFIED") != -1) {
                showMenu(avatar, TRUE);
            } else if (
                llSubStringIndex(body, "AVATAR_NOT_LINKED") != -1
            ) {
                showMenu(avatar, FALSE);
            } else {
                llInstantMessage(
                    avatar,
                    "Unable to determine avatar verification status."
                );
            }
            return;
        }

        if (action == "link") {
            llInstantMessage(
                avatar,
                "Return to Moodle and confirm your avatar. "
                + "After confirmation, touch the kiosk again."
            );
            return;
        }

        llInstantMessage(
            avatar,
            "SLBridge response: " + llGetSubString(body, 0, 300)
        );
    }
}
