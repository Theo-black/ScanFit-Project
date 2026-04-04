import sys

import cv2


def main() -> int:
    data = sys.stdin.buffer.read().decode("utf-8").strip()
    if not data:
        return 1

    params = cv2.QRCodeEncoder_Params()
    params.version = 0
    params.correction_level = cv2.QRCodeEncoder_CORRECT_LEVEL_M

    encoder = cv2.QRCodeEncoder_create(params)
    qr_image = encoder.encode(data)

    # Add a quiet zone and scale the matrix so authenticator apps can scan it reliably.
    qr_image = cv2.copyMakeBorder(qr_image, 4, 4, 4, 4, cv2.BORDER_CONSTANT, value=255)
    qr_image = cv2.resize(qr_image, None, fx=8, fy=8, interpolation=cv2.INTER_NEAREST)

    success, encoded = cv2.imencode(".png", qr_image)
    if not success:
        return 1

    sys.stdout.buffer.write(encoded.tobytes())
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
