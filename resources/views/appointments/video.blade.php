@extends('layouts.app')

@section('title', 'Video consultation')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy">
            <span class="page-eyebrow">PRIVATE CONSULTATION</span>
            <h1>Video visit</h1>
            <p>
                Appointment #{{ $appointment->id }} ·
                {{ $appointment->scheduled_at->format('M j, Y g:i A') }} {{ config('app.timezone') }}
            </p>
        </div>
        <span id="call-status" class="badge text-bg-secondary" role="status" aria-live="polite">Ready to connect</span>
    </header>
    <section class="card overflow-hidden">
        <div class="surface-header">
            <div><h2>Your secure visit room</h2><p>The call begins when you choose to join.</p></div>
            <span class="soft-pill">{{ $jitsiDomain }} · demo</span>
        </div>
        <div class="card-body text-center" id="join-prompt">
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">◉</span>
                <strong>Everything ready for your visit?</strong>
                <p>Check that you’re somewhere comfortable. You can enable your camera and microphone when you join.</p>
                <button class="btn btn-primary mt-2" id="join-call" type="button">Join video call <span aria-hidden="true">→</span></button>
            </div>
        </div>
        <div id="jitsi-meet" class="video-room"></div>
        <div class="card-body video-controls d-flex flex-wrap justify-content-center gap-2">
            <button id="mute-toggle" class="btn btn-outline-secondary" type="button" aria-pressed="false" disabled>Mute microphone</button>
            <button id="camera-toggle" class="btn btn-outline-secondary" type="button" aria-pressed="false" disabled>Turn camera off</button>
            <button id="end-call" class="btn btn-danger" type="button">Leave call</button>
        </div>
    </section>
    <details class="video-privacy-note mt-3">
        <summary>Important video privacy note</summary>
        <div class="small mt-2">This demo uses the public Jitsi Meet service at {{ $jitsiDomain }}. It is not configured as a HIPAA-compliant service. App access is restricted to appointment participants, but anyone who obtains the random meeting link may be able to join. Do not discuss or share highly sensitive health information in this demo call.</div>
    </details>

    <script>
        const joinButton = document.getElementById('join-call');
        const endButton = document.getElementById('end-call');
        const callStatus = document.getElementById('call-status');
        const muteButton = document.getElementById('mute-toggle');
        const cameraButton = document.getElementById('camera-toggle');
        const meetingContainer = document.getElementById('jitsi-meet');
        const jitsiDomain = @js($jitsiDomain);
        const roomName = @js($roomName);
        const displayName = @js($displayName);
        const endCallUrl = @js($endCallUrl);
        let meetingApi = null;

        function leaveCall() {
            window.location.assign(endCallUrl);
        }

        function setToggleState(button, isActive, activeText, inactiveText) {
            button.setAttribute('aria-pressed', String(isActive));
            button.textContent = isActive ? activeText : inactiveText;
        }

        joinButton.addEventListener('click', () => {
            joinButton.disabled = true;
            callStatus.textContent = 'Connecting to Jitsi…';
            const script = document.createElement('script');
            script.src = `https://${jitsiDomain}/external_api.js`;
            script.async = true;
            script.onerror = () => {
                callStatus.textContent = 'Could not load the video service. Check your connection and try again.';
                joinButton.disabled = false;
            };
            script.onload = () => {
                try {
                    meetingApi = new window.JitsiMeetExternalAPI(jitsiDomain, {
                        roomName,
                        width: '100%',
                        height: '68vh',
                        parentNode: meetingContainer,
                        userInfo: {displayName},
                    });

                    meetingApi.addListener('videoConferenceJoined', () => {
                        callStatus.textContent = 'Connected';
                        muteButton.disabled = false;
                        cameraButton.disabled = false;
                        joinButton.hidden = true;
                    });
                    meetingApi.addListener('videoConferenceLeft', leaveCall);
                    meetingApi.addListener('audioMuteStatusChanged', (event) => {
                        setToggleState(muteButton, event.muted, 'Unmute microphone', 'Mute microphone');
                    });
                    meetingApi.addListener('cameraMuteStatusChanged', (event) => {
                        setToggleState(cameraButton, event.muted, 'Turn camera on', 'Turn camera off');
                    });
                    meetingApi.addListener('errorOccurred', (event) => {
                        if (event.isFatal) {
                            callStatus.textContent = 'The video service reported an error. Leave and rejoin the call.';
                        }
                    });
                } catch {
                    callStatus.textContent = 'The video service could not start. Check your browser and try again.';
                    joinButton.disabled = false;
                }
            };
            document.head.appendChild(script);
        });

        muteButton.addEventListener('click', () => meetingApi?.executeCommand('toggleAudio'));
        cameraButton.addEventListener('click', () => meetingApi?.executeCommand('toggleVideo'));
        endButton.addEventListener('click', () => {
            if (meetingApi) {
                meetingApi.executeCommand('hangup');
                window.setTimeout(leaveCall, 1500);
                return;
            }

            leaveCall();
        });
    </script>
@endsection
