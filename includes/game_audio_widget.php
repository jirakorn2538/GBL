<!-- Floating Audio Control Widget for Money Life Game -->
<div class="audio-floating-control" id="audioControlWidget">
    <button type="button" class="audio-btn" id="bgmToggleBtn" onclick="toggleBgm()" title="เปิด/ปิดดนตรีประกอบ">
        🎵 <span class="btn-label">ดนตรี</span>
        <span class="eq-bars"><span></span><span></span><span></span></span>
    </button>
    <span style="color: #cbd5e1;">|</span>
    <button type="button" class="audio-btn" id="soundToggleBtn" onclick="toggleSound()" title="เปิด/ปิดเสียงพูดและเอฟเฟกต์">
        🔊 <span class="btn-label">เสียง</span>
    </button>
    <span style="color: #cbd5e1;">|</span>
    <button type="button" class="audio-btn" onclick="window.gameAudio && window.gameAudio.stopSpeaking()" title="หยุดเสียงพูด">
        ⏹️ <span class="btn-label">หยุดอ่าน</span>
    </button>
</div>

<!-- Canvas สำหรับ Confetti เฉลิมฉลอง (ทำงานเมื่อมีคลาส confetti-active) -->
<canvas id="confettiCanvas" class="confetti-canvas" style="display:none;"></canvas>

<script>
// ซิงค์สถานะปุ่มเสียงและดนตรีเมื่อโหลดหน้า
document.addEventListener('DOMContentLoaded', function() {
    if (window.gameAudio) {
        window.gameAudio.updateUi();
    }
});
// Confetti Animation Engine for Game Celebration
function triggerConfetti() {
    const canvas = document.getElementById('confettiCanvas');
    if (!canvas) return;
    canvas.style.display = 'block';
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const pieces = [];
    const colors = ['#14b8a6', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6', '#10b981'];

    for (let i = 0; i < 90; i++) {
        pieces.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            size: Math.random() * 9 + 6,
            color: colors[Math.floor(Math.random() * colors.length)],
            speedY: Math.random() * 3 + 2.5,
            speedX: Math.random() * 2 - 1,
            rotation: Math.random() * 360,
            rotSpeed: Math.random() * 4 - 2
        });
    }

    let animationFrame;
    let startTime = Date.now();

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        pieces.forEach(p => {
            p.y += p.speedY;
            p.x += p.speedX;
            p.rotation += p.rotSpeed;

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate((p.rotation * Math.PI) / 180);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
            ctx.restore();
        });

        if (Date.now() - startTime < 4500) {
            animationFrame = requestAnimationFrame(render);
        } else {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.style.display = 'none';
        }
    }
    render();
}
</script>
