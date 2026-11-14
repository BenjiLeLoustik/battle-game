const MOODS = [
    { root: 45, tempo: 92, chords: [[0, 3, 7], [-4, 0, 3], [3, 7, 10], [-2, 2, 5]] },
    { root: 43, tempo: 100, chords: [[0, 3, 7], [5, 8, 12], [-2, 2, 5], [3, 7, 10]] },
    { root: 40, tempo: 108, chords: [[0, 3, 7], [-4, 0, 3], [-2, 2, 5], [-5, -2, 2]] },
    { root: 50, tempo: 96, chords: [[0, 3, 7], [3, 7, 10], [5, 8, 12], [-2, 2, 5]] },
    { root: 48, tempo: 116, chords: [[0, 4, 7], [-3, 0, 4], [5, 9, 12], [7, 11, 14]] },
];
const ARPEGGIOS = [[0, 1, 2, 1, 0, 1, 2, 3], [0, 2, 1, 2, 3, 2, 1, 2], [0, 1, 2, 3, 2, 1, 0, 2]];
const STORAGE = { effects: 'bg-sound-effects', music: 'bg-sound-music' };

const hash = (text) => [...text].reduce((value, character) => (value * 31 + character.charCodeAt(0)) >>> 0, 7);
const frequency = (note) => 440 * 2 ** ((note - 69) / 12);

const remember = (key, value) => {
    try {
        return value === undefined ? localStorage.getItem(key) : localStorage.setItem(key, value);
    } catch {
        return null;
    }
};

export default function sound({ slug = '', effects = 0.7, music = 0.35 } = {}) {
    const seed = hash(slug);
    const mood = MOODS[seed % MOODS.length];
    const arpeggio = ARPEGGIOS[seed % ARPEGGIOS.length];
    const volumes = { effects: effects || 0.7, music: music || 0.35 };
    const enabled = {
        effects: (remember(STORAGE.effects) ?? (effects > 0 ? '1' : '0')) === '1',
        music: (remember(STORAGE.music) ?? (music > 0 ? '1' : '0')) === '1',
    };

    let audio = null;
    let buses = null;
    let noise = null;
    let wantMusic = false;
    let scheduler = null;
    let step = 0;
    let nextAt = 0;
    const listeners = new Set();

    const setup = () => {
        if (audio) {
            return audio;
        }

        const Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) {
            return null;
        }

        audio = new Context();
        const compressor = audio.createDynamicsCompressor();
        compressor.threshold.value = -14;
        compressor.ratio.value = 4;
        compressor.connect(audio.destination);
        buses = { effects: audio.createGain(), music: audio.createGain(), delay: audio.createDelay(1) };
        buses.effects.gain.value = enabled.effects ? volumes.effects * 0.6 : 0;
        buses.music.gain.value = 0;
        buses.effects.connect(compressor);
        buses.music.connect(compressor);
        const feedback = audio.createGain();
        const wet = audio.createGain();
        buses.delay.delayTime.value = 60 / mood.tempo * 0.75;
        feedback.gain.value = 0.32;
        wet.gain.value = 0.35;
        buses.delay.connect(feedback);
        feedback.connect(buses.delay);
        buses.delay.connect(wet);
        wet.connect(buses.music);
        noise = audio.createBuffer(1, audio.sampleRate, audio.sampleRate);
        const samples = noise.getChannelData(0);

        for (let index = 0; index < samples.length; index += 1) {
            samples[index] = Math.random() * 2 - 1;
        }

        return audio;
    };

    const unlock = () => {
        if (!setup()) {
            return;
        }

        if (audio.state === 'suspended') {
            audio.resume();
        }

        if (wantMusic && enabled.music && scheduler === null) {
            beginMusic();
        }
    };

    ['pointerdown', 'keydown', 'touchstart'].forEach((type) => window.addEventListener(type, unlock, { passive: true }));

    const ready = () => audio !== null && audio.state === 'running';

    const tone = ({ type = 'sine', from = 440, to = null, at = 0, duration = 0.12, volume = 0.3, attack = 0.005, bus = 'effects', filter = null, send = false }) => {
        const start = audio.currentTime + at;
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();
        oscillator.type = type;
        oscillator.frequency.setValueAtTime(from, start);

        if (to !== null) {
            oscillator.frequency.exponentialRampToValueAtTime(Math.max(20, to), start + duration);
        }

        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(volume, start + attack);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        let output = oscillator;

        if (filter) {
            const lowpass = audio.createBiquadFilter();
            lowpass.type = 'lowpass';
            lowpass.frequency.setValueAtTime(filter, start);
            lowpass.frequency.exponentialRampToValueAtTime(Math.max(200, filter / 4), start + duration);
            oscillator.connect(lowpass);
            output = lowpass;
        }

        output.connect(gain);
        gain.connect(buses[bus]);

        if (send) {
            gain.connect(buses.delay);
        }

        oscillator.start(start);
        oscillator.stop(start + duration + 0.05);
    };

    const hiss = ({ at = 0, duration = 0.08, volume = 0.2, kind = 'bandpass', frequency: center = 2000, bus = 'effects', to = null }) => {
        const start = audio.currentTime + at;
        const source = audio.createBufferSource();
        const filter = audio.createBiquadFilter();
        const gain = audio.createGain();
        source.buffer = noise;
        filter.type = kind;
        filter.frequency.setValueAtTime(center, start);

        if (to !== null) {
            filter.frequency.exponentialRampToValueAtTime(to, start + duration);
        }

        gain.gain.setValueAtTime(volume, start);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        source.connect(filter);
        filter.connect(gain);
        gain.connect(buses[bus]);
        source.start(start, Math.random() * 0.5);
        source.stop(start + duration + 0.02);
    };

    const notes = (list, { type = 'triangle', gap = 0.09, duration = 0.16, volume = 0.22 } = {}) => list.forEach((note, index) => tone({ type, from: frequency(note), at: index * gap, duration, volume }));

    const EFFECTS = {
        click: () => tone({ type: 'square', from: 1400, to: 900, duration: 0.035, volume: 0.05, filter: 3000 }),
        select: () => notes([79, 84], { gap: 0.05, duration: 0.09, volume: 0.12 }),
        move: () => tone({ type: 'sine', from: 330, to: 520, duration: 0.11, volume: 0.18 }),
        place: () => {
            tone({ type: 'triangle', from: 260, to: 180, duration: 0.14, volume: 0.25 });
            hiss({ duration: 0.05, volume: 0.08, frequency: 1800 });
        },
        gain: () => notes([76, 81, 88], { gap: 0.06, duration: 0.12, volume: 0.16 }),
        correct: () => notes([72, 79], { type: 'sine', gap: 0.08, duration: 0.2, volume: 0.22 }),
        wrong: () => tone({ type: 'sawtooth', from: 220, to: 130, duration: 0.28, volume: 0.14, filter: 1200 }),
        turn: () => notes([79, 84], { type: 'sine', gap: 0.11, duration: 0.22, volume: 0.2 }),
        round: () => {
            tone({ type: 'sine', from: 280, to: 880, duration: 0.3, volume: 0.12 });
            hiss({ duration: 0.3, volume: 0.06, kind: 'bandpass', frequency: 600, to: 4000 });
        },
        reveal: () => notes([67, 74, 79], { type: 'sine', gap: 0.05, duration: 0.25, volume: 0.12 }),
        tick: () => tone({ type: 'square', from: 1000, duration: 0.05, volume: 0.07, filter: 2500 }),
        go: () => notes([84, 91], { type: 'square', gap: 0.07, duration: 0.25, volume: 0.09 }),
        win: () => {
            notes([72, 76, 79, 84], { gap: 0.12, duration: 0.22, volume: 0.2 });
            [72, 76, 79, 84].forEach((note) => tone({ type: 'triangle', from: frequency(note), at: 0.5, duration: 0.9, volume: 0.1, attack: 0.02 }));
        },
        lose: () => notes([67, 65, 63, 60], { type: 'sine', gap: 0.16, duration: 0.32, volume: 0.18 }),
        deal: () => hiss({ duration: 0.07, volume: 0.16, kind: 'highpass', frequency: 2500 }),
        flip: () => {
            hiss({ duration: 0.05, volume: 0.12, kind: 'bandpass', frequency: 3200 });
            tone({ type: 'sine', from: 900, to: 1300, duration: 0.05, volume: 0.06 });
        },
        roll: () => Array.from({ length: 7 }, (_, index) => hiss({ at: index * 0.045 + Math.random() * 0.02, duration: 0.03, volume: 0.18, frequency: 1200 + Math.random() * 1800 })),
        shoot: () => {
            tone({ type: 'sawtooth', from: 1100, to: 220, duration: 0.16, volume: 0.1, filter: 4000 });
            hiss({ duration: 0.12, volume: 0.06, frequency: 3000, to: 800 });
        },
        hit: () => {
            tone({ type: 'sine', from: 160, to: 55, duration: 0.22, volume: 0.35 });
            hiss({ duration: 0.06, volume: 0.12, kind: 'lowpass', frequency: 1500 });
        },
        pop: () => tone({ type: 'sine', from: 480, to: 960, duration: 0.08, volume: 0.18 }),
        capture: () => {
            tone({ type: 'triangle', from: 700, to: 300, duration: 0.18, volume: 0.18 });
            hiss({ at: 0.03, duration: 0.08, volume: 0.08, frequency: 2400 });
        },
        bounce: () => tone({ type: 'sine', from: 620, to: 520, duration: 0.05, volume: 0.14 }),
        drop: () => tone({ type: 'sine', from: 900 + Math.random() * 300, to: 500, duration: 0.07, volume: 0.1 }),
        goal: () => {
            hiss({ duration: 0.9, volume: 0.1, kind: 'bandpass', frequency: 900, to: 1600 });
            notes([72, 76, 79], { gap: 0.1, duration: 0.3, volume: 0.15 });
        },
    };

    const play = (name) => {
        if (!enabled.effects || !EFFECTS[name]) {
            return;
        }

        if (!ready()) {
            unlock();

            if (!ready()) {
                return;
            }
        }

        EFFECTS[name]();
    };

    const schedule = () => {
        const sixteenth = 60 / mood.tempo / 4;

        while (nextAt < audio.currentTime + 0.15) {
            const bar = Math.floor(step / 16) % mood.chords.length;
            const beat = step % 16;
            const chord = mood.chords[bar];
            const at = nextAt - audio.currentTime;

            if (beat % 4 === 0) {
                tone({ type: 'sine', from: 120, to: 45, at, duration: 0.18, volume: beat === 0 ? 0.32 : 0.2, bus: 'music' });
            }

            if (beat % 4 === 2) {
                hiss({ at, duration: 0.04, volume: 0.05, kind: 'highpass', frequency: 7000, bus: 'music' });
            }

            if (beat % 2 === 0) {
                tone({ type: 'sawtooth', from: frequency(mood.root - 12 + chord[0]), at, duration: sixteenth * 1.8, volume: 0.09, bus: 'music', filter: 520 });
            }

            if (beat % 2 === 1 || beat === 0) {
                const index = arpeggio[(beat >> 1) % arpeggio.length];
                const note = mood.root + 12 + (chord[index] ?? chord[0] + 12);
                tone({ type: 'triangle', from: frequency(note), at, duration: sixteenth * 1.6, volume: 0.05, bus: 'music', send: true, filter: 3200 });
            }

            if (beat === 0) {
                chord.forEach((offset) => tone({ type: 'sawtooth', from: frequency(mood.root + offset), at, duration: sixteenth * 15, volume: 0.018, attack: 0.4, bus: 'music', filter: 900 }));
            }

            nextAt += sixteenth;
            step += 1;
        }
    };

    const beginMusic = () => {
        if (!ready() || scheduler !== null) {
            return;
        }

        step = 0;
        nextAt = audio.currentTime + 0.1;
        buses.music.gain.cancelScheduledValues(audio.currentTime);
        buses.music.gain.setValueAtTime(0.0001, audio.currentTime);
        buses.music.gain.exponentialRampToValueAtTime(volumes.music * 0.8, audio.currentTime + 2.5);
        scheduler = setInterval(schedule, 40);
        schedule();
    };

    const endMusic = (fade = 1.2) => {
        if (scheduler === null) {
            return;
        }

        const timer = scheduler;
        scheduler = null;
        buses.music.gain.cancelScheduledValues(audio.currentTime);
        buses.music.gain.setValueAtTime(Math.max(0.0001, buses.music.gain.value), audio.currentTime);
        buses.music.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + fade);
        setTimeout(() => clearInterval(timer), fade * 1000);
    };

    document.addEventListener('visibilitychange', () => {
        if (!audio) {
            return;
        }

        if (document.hidden) {
            audio.suspend();
        } else if (wantMusic || enabled.effects) {
            audio.resume();
        }
    });

    const toggle = (kind) => {
        enabled[kind] = !enabled[kind];
        remember(STORAGE[kind], enabled[kind] ? '1' : '0');
        unlock();

        if (kind === 'effects' && buses) {
            buses.effects.gain.value = enabled.effects ? volumes.effects * 0.6 : 0;
        }

        if (kind === 'music') {
            enabled.music && wantMusic ? beginMusic() : endMusic(0.4);
        }

        listeners.forEach((listener) => listener({ ...enabled }));

        return enabled[kind];
    };

    return {
        play,
        toggle,
        unlock,
        enabled: () => ({ ...enabled }),
        onChange: (listener) => listeners.add(listener),
        startMusic: () => {
            wantMusic = true;

            if (enabled.music) {
                unlock();
                beginMusic();
            }
        },
        stopMusic: (fade) => {
            wantMusic = false;
            endMusic(fade);
        },
    };
}
