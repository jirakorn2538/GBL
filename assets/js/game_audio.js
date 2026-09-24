/**
 * Money Life - Web Audio API Sound Effects, Background Music & Thai Speech Synthesis Engine
 * No external audio files needed - runs 100% locally and offline in any browser
 */

class GameAudioManager {
    constructor() {
        this.audioCtx = null;
        this.isMuted = localStorage.getItem('money_life_muted') === 'true';
        this.isBgmEnabled = localStorage.getItem('money_life_bgm') !== 'false'; // default true
        this.synth = window.speechSynthesis || null;
        this.currentUtterance = null;
        this.thaiVoice = null;

        // BGM Sequencer state
        this.bgmPlaying = false;
        this.bgmGainNode = null;
        this.bgmMasterGain = 0.09; // soft, pleasant volume
        this.bgmTimer = null;
        this.tempo = 124; // 124 BPM upbeat playful tempo
        this.stepIndex = 0;
        this.nextNoteTime = 0;

        this.initVoices();
        if (this.synth && this.synth.onvoiceschanged !== undefined) {
            this.synth.onvoiceschanged = () => this.initVoices();
        }

        // Auto start BGM on first user interaction if enabled
        this.setupAutostartListener();
    }

    // Lazy initialize AudioContext on user interaction
    getAudioContext() {
        if (!this.audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.audioCtx = new AudioContext();
            }
        }
        if (this.audioCtx && this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }
        return this.audioCtx;
    }

    initVoices() {
        if (!this.synth) return;
        const voices = this.synth.getVoices();
        this.thaiVoice = voices.find(v => v.lang === 'th-TH' || v.lang.startsWith('th')) || null;
    }

    setupAutostartListener() {
        const startAudioOnce = () => {
            const ctx = this.getAudioContext();
            if (ctx && this.isBgmEnabled && !this.bgmPlaying && !this.isMuted) {
                this.startBgm();
            }
            document.removeEventListener('click', startAudioOnce);
            document.removeEventListener('keydown', startAudioOnce);
            document.removeEventListener('touchstart', startAudioOnce);
        };

        document.addEventListener('click', startAudioOnce, { once: true });
        document.addEventListener('keydown', startAudioOnce, { once: true });
        document.addEventListener('touchstart', startAudioOnce, { once: true });
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        localStorage.setItem('money_life_muted', this.isMuted);
        if (this.isMuted) {
            this.stopSpeaking();
            this.stopBgm();
        } else {
            this.playBfx('coin');
            if (this.isBgmEnabled) {
                this.startBgm();
            }
        }
        this.updateUi();
        return this.isMuted;
    }

    toggleBgm() {
        this.isBgmEnabled = !this.isBgmEnabled;
        localStorage.setItem('money_life_bgm', this.isBgmEnabled);
        if (this.isBgmEnabled && !this.isMuted) {
            this.getAudioContext();
            this.startBgm();
            this.playBfx('click');
        } else {
            this.stopBgm();
        }
        this.updateUi();
        return this.isBgmEnabled;
    }

    updateUi() {
        const muteBtn = document.getElementById('soundToggleBtn');
        if (muteBtn) {
            muteBtn.innerHTML = this.isMuted 
                ? '🔇 <span class="btn-label">เสียง: ปิด</span>' 
                : '🔊 <span class="btn-label">เสียง: เปิด</span>';
            muteBtn.classList.toggle('muted', this.isMuted);
        }

        const bgmBtn = document.getElementById('bgmToggleBtn');
        if (bgmBtn) {
            if (this.bgmPlaying) {
                bgmBtn.classList.add('music-playing');
                bgmBtn.innerHTML = '🎵 <span class="btn-label">ดนตรี: เปิด</span> <span class="eq-bars"><span></span><span></span><span></span></span>';
            } else {
                bgmBtn.classList.remove('music-playing');
                bgmBtn.innerHTML = '🎵 <span class="btn-label">ดนตรี: ปิด</span>';
            }
        }
    }

    // Ducking: lower BGM volume when Thai speech synthesis is reading
    duckBgm(duck) {
        if (!this.bgmGainNode || !this.audioCtx) return;
        try {
            const now = this.audioCtx.currentTime;
            const targetGain = duck ? 0.02 : (this.isMuted ? 0 : this.bgmMasterGain);
            this.bgmGainNode.gain.cancelScheduledValues(now);
            this.bgmGainNode.gain.linearRampToValueAtTime(targetGain, now + 0.3);
        } catch (e) {}
    }

    // Playful, cheerful synthesized BGM sequencer
    startBgm() {
        if (this.bgmPlaying || this.isMuted || !this.isBgmEnabled) return;
        const ctx = this.getAudioContext();
        if (!ctx) return;

        if (!this.bgmGainNode) {
            this.bgmGainNode = ctx.createGain();
            this.bgmGainNode.gain.setValueAtTime(this.bgmMasterGain, ctx.currentTime);
            this.bgmGainNode.connect(ctx.destination);
        } else {
            this.bgmGainNode.gain.setValueAtTime(this.bgmMasterGain, ctx.currentTime);
        }

        this.bgmPlaying = true;
        this.stepIndex = 0;
        this.nextNoteTime = ctx.currentTime + 0.05;

        // Scheduler interval loop
        const secondsPerBeat = 60.0 / this.tempo;
        const stepTime = secondsPerBeat / 4; // 16th note steps

        // Upbeat, cute 4-bar game loop (C major - A minor - F major - G major)
        // Melody frequencies:
        const notes = {
            C4: 261.63, D4: 293.66, E4: 329.63, F4: 349.23, G4: 392.00, A4: 440.00, B4: 493.88,
            C5: 523.25, D5: 587.33, E5: 659.25, F5: 698.46, G5: 783.99, A5: 880.00,
            C3: 130.81, A2: 110.00, F2: 87.31, G2: 98.00
        };

        // 64-step melody array (4 bars * 16 steps)
        const melody = [
            // Bar 1: C major
            notes.C5, 0, notes.E5, 0, notes.G5, notes.E5, 0, notes.G5,
            notes.A5, 0, notes.G5, 0, notes.E5, notes.D5, notes.C5, 0,
            // Bar 2: A minor
            notes.C5, 0, notes.E5, 0, notes.A5, notes.G5, 0, notes.E5,
            notes.D5, 0, notes.C5, 0, notes.D5, 0, notes.E5, 0,
            // Bar 3: F major
            notes.A4, 0, notes.C5, 0, notes.F5, notes.E5, 0, notes.C5,
            notes.D5, 0, notes.C5, 0, notes.A4, 0, notes.C5, 0,
            // Bar 4: G major
            notes.B4, 0, notes.D5, 0, notes.G5, notes.F5, 0, notes.D5,
            notes.C5, 0, notes.D5, 0, notes.E5, 0, notes.D5, 0
        ];

        // Bassline array (notes triggered at specific 16th note steps)
        const bass = [
            // Bar 1
            notes.C3, 0, 0, 0, 0, 0, 0, 0, notes.C3, 0, 0, 0, notes.G2, 0, 0, 0,
            // Bar 2
            notes.A2, 0, 0, 0, 0, 0, 0, 0, notes.A2, 0, 0, 0, notes.E2, 0, 0, 0,
            // Bar 3
            notes.F2, 0, 0, 0, 0, 0, 0, 0, notes.F2, 0, 0, 0, notes.C3, 0, 0, 0,
            // Bar 4
            notes.G2, 0, 0, 0, 0, 0, 0, 0, notes.G2, 0, 0, 0, notes.B2, 0, 0, 0
        ];

        const scheduleNote = (time, step) => {
            const melFreq = melody[step % melody.length];
            const bassFreq = bass[step % bass.length];

            // 1. Play melody note (bright, warm marimba pluck)
            if (melFreq > 0) {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(melFreq, time);

                // Pluck envelope
                gain.gain.setValueAtTime(0.35, time);
                gain.gain.exponentialRampToValueAtTime(0.001, time + 0.18);

                osc.connect(gain);
                gain.connect(this.bgmGainNode);

                osc.start(time);
                osc.stop(time + 0.18);
            }

            // 2. Play bass note (gentle bouncy sine bass)
            if (bassFreq > 0) {
                const bassOsc = ctx.createOscillator();
                const bassGain = ctx.createGain();
                bassOsc.type = 'sine';
                bassOsc.frequency.setValueAtTime(bassFreq, time);

                bassGain.gain.setValueAtTime(0.4, time);
                bassGain.gain.exponentialRampToValueAtTime(0.01, time + 0.28);

                bassOsc.connect(bassGain);
                bassGain.connect(this.bgmGainNode);

                bassOsc.start(time);
                bassOsc.stop(time + 0.28);
            }

            // 3. Play light percussive click on 4th beats
            if (step % 4 === 0) {
                const noiseOsc = ctx.createOscillator();
                const noiseGain = ctx.createGain();
                noiseOsc.type = 'sine';
                noiseOsc.frequency.setValueAtTime(step % 8 === 0 ? 180 : 260, time);
                noiseGain.gain.setValueAtTime(0.08, time);
                noiseGain.gain.exponentialRampToValueAtTime(0.001, time + 0.04);

                noiseOsc.connect(noiseGain);
                noiseGain.connect(this.bgmGainNode);

                noiseOsc.start(time);
                noiseOsc.stop(time + 0.04);
            }
        };

        const scheduleLoop = () => {
            if (!this.bgmPlaying) return;
            const lookahead = 0.15; // schedule 150ms ahead
            while (this.nextNoteTime < ctx.currentTime + lookahead) {
                scheduleNote(this.nextNoteTime, this.stepIndex);
                this.nextNoteTime += stepTime;
                this.stepIndex++;
            }
        };

        this.bgmTimer = setInterval(scheduleLoop, 45);
        this.updateUi();
    }

    stopBgm() {
        if (!this.bgmPlaying) return;
        this.bgmPlaying = false;
        if (this.bgmTimer) {
            clearInterval(this.bgmTimer);
            this.bgmTimer = null;
        }
        if (this.bgmGainNode && this.audioCtx) {
            try {
                this.bgmGainNode.gain.linearRampToValueAtTime(0, this.audioCtx.currentTime + 0.1);
            } catch (e) {}
        }
        this.updateUi();
    }

    // Synthesized Sound Effects using Web Audio API
    playBfx(type) {
        if (this.isMuted) return;
        try {
            const ctx = this.getAudioContext();
            if (!ctx) return;

            const now = ctx.currentTime;

            if (type === 'click') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(600, now);
                osc.frequency.exponentialRampToValueAtTime(300, now + 0.06);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.06);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.06);
            } 
            else if (type === 'coin') {
                // Bright two-tone coin sound (B5 -> E6)
                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                const gain = ctx.createGain();

                osc1.type = 'sine';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(987.77, now); // B5
                osc2.frequency.setValueAtTime(1318.51, now + 0.08); // E6

                gain.gain.setValueAtTime(0.25, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.35);

                osc1.connect(gain);
                osc2.connect(gain);
                gain.connect(ctx.destination);

                osc1.start(now);
                osc1.stop(now + 0.08);
                osc2.start(now + 0.08);
                osc2.stop(now + 0.35);
            } 
            else if (type === 'success') {
                // Happy chord progression (C5 -> E5 -> G5 -> C6)
                const freqs = [523.25, 659.25, 783.99, 1046.50];
                freqs.forEach((freq, idx) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const startTime = now + (idx * 0.09);
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(freq, startTime);
                    gain.gain.setValueAtTime(0.2, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.4);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(startTime);
                    osc.stop(startTime + 0.4);
                });
            } 
            else if (type === 'warning') {
                // Low alert chord
                const freqs = [320, 280];
                freqs.forEach((freq, idx) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const startTime = now + (idx * 0.12);
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(freq, startTime);
                    gain.gain.setValueAtTime(0.18, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, startTime + 0.25);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(startTime);
                    osc.stop(startTime + 0.25);
                });
            }
            else if (type === 'fanfare') {
                // Victory Fanfare for final game completion
                const notes = [
                    {f: 523.25, t: 0.0, d: 0.12},
                    {f: 523.25, t: 0.13, d: 0.12},
                    {f: 523.25, t: 0.26, d: 0.12},
                    {f: 659.25, t: 0.40, d: 0.35},
                    {f: 783.99, t: 0.78, d: 0.25},
                    {f: 1046.50, t: 1.05, d: 0.70}
                ];
                notes.forEach(n => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const startTime = now + n.t;
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(n.f, startTime);
                    gain.gain.setValueAtTime(0.3, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + n.d);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(startTime);
                    osc.stop(startTime + n.d);
                });
            }
        } catch (e) {
            console.warn('Audio Bfx error:', e);
        }
    }

    // Thai Speech Synthesis with BGM ducking
    speakThai(text, onEndCallback = null) {
        if (this.isMuted || !this.synth) return;

        // Clean formatting symbols before reading
        let cleanText = text
            .replace(/[🟢🟡🔴⭐💡👤🎮▶️➡️⬅️🔄📱🛠️💸🚑💊🚨]/g, '')
            .replace(/\s+/g, ' ')
            .trim();

        if (!cleanText) return;

        this.stopSpeaking();

        try {
            const utter = new SpeechSynthesisUtterance(cleanText);
            utter.lang = 'th-TH';
            utter.rate = 1.0;
            utter.pitch = 1.1;

            if (this.thaiVoice) {
                utter.voice = this.thaiVoice;
            }

            // Lower BGM during speech
            this.duckBgm(true);
            document.body.classList.add('is-speaking');

            utter.onend = () => {
                document.body.classList.remove('is-speaking');
                this.duckBgm(false);
                this.currentUtterance = null;
                if (onEndCallback) onEndCallback();
            };

            utter.onerror = () => {
                document.body.classList.remove('is-speaking');
                this.duckBgm(false);
                this.currentUtterance = null;
            };

            this.currentUtterance = utter;
            this.synth.speak(utter);
        } catch (err) {
            console.warn('Speech synthesis error:', err);
            this.duckBgm(false);
        }
    }

    stopSpeaking() {
        if (this.synth) {
            this.synth.cancel();
        }
        document.body.classList.remove('is-speaking');
        this.duckBgm(false);
        this.currentUtterance = null;
    }
}

// Global instance
window.gameAudio = new GameAudioManager();

// Helper functions for easy HTML binding
function speakText(text) {
    if (window.gameAudio) {
        window.gameAudio.getAudioContext();
        window.gameAudio.speakThai(text);
    }
}

function playSound(type) {
    if (window.gameAudio) {
        window.gameAudio.playBfx(type);
    }
}

function toggleSound() {
    if (window.gameAudio) {
        window.gameAudio.getAudioContext();
        window.gameAudio.toggleMute();
    }
}

function toggleBgm() {
    if (window.gameAudio) {
        window.gameAudio.getAudioContext();
        window.gameAudio.toggleBgm();
    }
}
