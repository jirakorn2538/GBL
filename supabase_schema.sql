-- ==========================================================
-- Money Life - Supabase PostgreSQL Database Schema
-- Project: jirakorn2538's Project (fsndkjviwlxavydglxcw)
-- How to use:
-- 1. Open Supabase Dashboard: https://supabase.com/dashboard/project/fsndkjviwlxavydglxcw
-- 2. Go to "SQL Editor" in the left sidebar
-- 3. Click "New Query", paste this entire script, and click "Run"
-- ==========================================================

-- 1. ตารางนักเรียน (students)
CREATE TABLE IF NOT EXISTS public.students (
    id BIGSERIAL PRIMARY KEY,
    student_code VARCHAR(50),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    class_level VARCHAR(20) NOT NULL,
    class_room VARCHAR(20) NOT NULL,
    student_number INT NOT NULL,
    photo TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'pending', 'rejected')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. ตารางรอบการเล่นเกม (game_sessions)
CREATE TABLE IF NOT EXISTS public.game_sessions (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES public.students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    total_score INT NOT NULL DEFAULT 0,
    max_score INT NOT NULL DEFAULT 50,
    percentage NUMERIC(5,2) NOT NULL DEFAULT 0.00,
    started_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMPTZ
);

-- 3. ตารางคำตอบของแต่ละสถานการณ์ (game_answers)
CREATE TABLE IF NOT EXISTS public.game_answers (
    id BIGSERIAL PRIMARY KEY,
    session_id BIGINT NOT NULL REFERENCES public.game_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    scenario_number INT NOT NULL,
    selected_option VARCHAR(255) NOT NULL,
    score INT NOT NULL DEFAULT 0,
    answered_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 4. ตารางครูและผู้ดูแลระบบ (teachers)
CREATE TABLE IF NOT EXISTS public.teachers (
    id BIGSERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL DEFAULT 'ครูผู้สอน',
    national_id VARCHAR(13) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    username VARCHAR(50),
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'suspended')),
    phone_verified SMALLINT NOT NULL DEFAULT 0,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role VARCHAR(20) NOT NULL DEFAULT 'teacher' CHECK (role IN ('teacher', 'admin')),
    otp_code VARCHAR(10),
    otp_expires_at TIMESTAMPTZ,
    is_active SMALLINT NOT NULL DEFAULT 1,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until TIMESTAMPTZ,
    two_factor_secret VARCHAR(255),
    two_factor_enabled SMALLINT NOT NULL DEFAULT 0,
    remember_token VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMPTZ,
    approved_by BIGINT,
    last_login_at TIMESTAMPTZ
);

-- 5. ตารางบันทึกประวัติกิจกรรม (audit_logs)
CREATE TABLE IF NOT EXISTS public.audit_logs (
    id BIGSERIAL PRIMARY KEY,
    teacher_id BIGINT,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id VARCHAR(50),
    details TEXT,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- สร้าง Indexes เพื่อความรวดเร็วในการค้นหา
CREATE INDEX IF NOT EXISTS idx_game_sessions_student ON public.game_sessions(student_id);
CREATE INDEX IF NOT EXISTS idx_game_answers_session ON public.game_answers(session_id);
CREATE INDEX IF NOT EXISTS idx_audit_teacher ON public.audit_logs(teacher_id);
CREATE INDEX IF NOT EXISTS idx_audit_created ON public.audit_logs(created_at);

-- ==========================================================
-- ข้อมูลเริ่มต้น: บัญชีผู้ดูแลระบบ (Admin)
-- User: 1329900585149
-- Pass: 0873565288
-- ==========================================================
INSERT INTO public.teachers (
    first_name,
    last_name,
    position,
    national_id,
    phone,
    username,
    password_hash,
    status,
    phone_verified,
    role,
    is_active,
    created_at,
    approved_at
) VALUES (
    'ผู้ดูแลระบบ',
    '(Admin)',
    'ผู้ดูแลระบบหลัก',
    '1329900585149',
    '0873565288',
    'admin',
    '$2y$10$j2.Dm8Ep37YHt.EOf4FNm.XGhgklQ.xAfCzbu8ci2vtDGkWG4a9ri', -- รหัสผ่าน: 0873565288
    'approved',
    1,
    'admin',
    1,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
) ON CONFLICT (national_id) DO UPDATE SET
    role = 'admin',
    status = 'approved',
    phone = EXCLUDED.phone,
    password_hash = EXCLUDED.password_hash;
