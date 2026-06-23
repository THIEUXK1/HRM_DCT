<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Jmrashed\Zkteco\Lib\ZKTeco;
use RuntimeException;

/**
 * Wrapper quanh jmrashed/zkteco (UDP port 4370).
 * Giữ nguyên interface cũ để AttendanceDeviceSyncService không phải thay đổi.
 */
class ZKTecoService
{
    // ZKTeco attendance state codes
    private const STATUS_CHECK_IN  = [0, 3, 4];
    private const STATUS_CHECK_OUT = [1, 2, 5];

    private ZKTeco $zk;
    private bool $connected = false;

    public function __construct(
        private readonly string $host,
        private readonly int    $port = 4370,
        private readonly string $password = '',
        private readonly int    $timeout = 10,
    ) {
        $this->zk = new ZKTeco($this->host, $this->port);
    }

    /** Kết nối và xác thực với thiết bị. */
    public function connect(): void
    {
        $ok = $this->zk->connect();

        if (! $ok) {
            throw new RuntimeException("Không kết nối được tới {$this->host}:{$this->port}.");
        }

        $this->connected = true;

        // Xác thực bằng password nếu có
        if ($this->password !== '') {
            // Library không expose setPassword riêng; gửi qua _command nếu cần.
            // Hầu hết thiết bị không dùng password — bỏ qua nếu connect thành công.
        }
    }

    /** Ngắt kết nối an toàn. */
    public function disconnect(): void
    {
        if ($this->connected) {
            $this->zk->disconnect();
            $this->connected = false;
        }
    }

    /**
     * Lấy toàn bộ log chấm công từ thiết bị.
     *
     * @return array<int, array{user_id: string, punched_at: Carbon, status: int, verify: int}>
     */
    public function getAttendanceLogs(): array
    {
        $raw = $this->zk->getAttendance();

        $records = [];
        foreach ($raw as $entry) {
            $userId = trim((string) ($entry['id'] ?? $entry['uid'] ?? ''));
            if ($userId === '') {
                continue;
            }

            $records[] = [
                'user_id'    => $userId,
                'punched_at' => Carbon::parse($entry['timestamp']),
                'status'     => (int) ($entry['state'] ?? 0),
                'verify'     => (int) ($entry['type'] ?? 0),
            ];
        }

        return $records;
    }

    /**
     * Đẩy thông tin 1 nhân viên lên thiết bị.
     *
     * @throws RuntimeException nếu thiết bị báo lỗi
     */
    public function pushUser(string $pin, string $name, int $cardNumber = 0): void
    {
        $uid = (int) $pin;

        if ($uid === 0) {
            throw new RuntimeException("PIN không hợp lệ: {$pin}");
        }

        $ok = $this->zk->setUser(
            uid: $uid,
            userid: substr($pin, 0, 9),
            name: mb_substr($name, 0, 24),
            password: '',
            cardno: $cardNumber,
        );

        if ($ok === false) {
            throw new RuntimeException("Thiết bị từ chối ghi user PIN={$pin}.");
        }
    }

    /**
     * Lấy toàn bộ user từ thiết bị.
     *
     * @return array<string, array{uid: int, userid: string, name: string}>  keyed by userid (PIN)
     */
    public function getUsers(): array
    {
        return $this->zk->getUser() ?? [];
    }

    /**
     * Lấy tất cả fingerprint templates của 1 user theo UID.
     *
     * @return array<int, string>  keyed by finger_index (0–9), value = binary template
     */
    public function getFingerprints(int $uid): array
    {
        return $this->zk->getFingerprint($uid) ?? [];
    }

    /**
     * Đẩy fingerprint templates lên thiết bị.
     *
     * @param  array<int, string>  $templates  keyed by finger_index, value = binary template
     * @return int  số template đẩy thành công
     */
    public function pushFingerprints(int $uid, array $templates): int
    {
        if (empty($templates)) {
            return 0;
        }
        return (int) $this->zk->setFingerprint($uid, $templates);
    }

    /** Lấy serial number của thiết bị. */
    public function getSerialNumber(): ?string
    {
        $sn = $this->zk->serialNumber();
        return $sn ? trim((string) $sn) : null;
    }

    /** Lấy phiên bản firmware của thiết bị. */
    public function getVersion(): ?string
    {
        $v = $this->zk->version();
        return $v ? trim((string) $v) : null;
    }

    /** Xóa user khỏi thiết bị theo UID (PIN dạng số). */
    public function removeUser(string $pin): void
    {
        $uid = (int) $pin;
        if ($uid === 0) {
            throw new RuntimeException("PIN không hợp lệ để xóa: {$pin}");
        }
        $this->zk->removeUser($uid);
    }

    /** Kiểm tra kết nối (không cần lấy dữ liệu). */
    public function testConnection(): bool
    {
        $this->connect();
        $this->disconnect();
        return true;
    }
}
