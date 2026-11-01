/**
 * HRIS Management System — Sprint 2, US-03
 *
 * Design Pattern: Adapter
 *
 * Adapts keyboard-style USB RFID reader input into
 * a consistent callback for the HRIS application.
 */
class RfidInputAdapter {
    constructor(inputElement, onIdentifierReceived) {
        if (!(inputElement instanceof HTMLInputElement)) {
            throw new TypeError(
                'RFID Adapter requires an HTML input element.'
            );
        }

        if (typeof onIdentifierReceived !== 'function') {
            throw new TypeError(
                'RFID Adapter requires a callback function.'
            );
        }

        this.inputElement = inputElement;
        this.onIdentifierReceived = onIdentifierReceived;
        this.connected = false;

        this.handleKeydown = this.handleKeydown.bind(this);
    }

    connect() {
        if (this.connected) {
            return;
        }

        this.inputElement.addEventListener(
            'keydown',
            this.handleKeydown
        );

        this.connected = true;
    }

    disconnect() {
        if (!this.connected) {
            return;
        }

        this.inputElement.removeEventListener(
            'keydown',
            this.handleKeydown
        );

        this.connected = false;
    }

    handleKeydown(event) {
        // The tested JT308 reader finishes a scan with Enter.
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        const identifier = this.normalize(
            this.inputElement.value
        );

        if (identifier === '') {
            return;
        }

        this.inputElement.value = '';
        this.onIdentifierReceived(identifier);
    }

    normalize(rawIdentifier) {
        if (typeof rawIdentifier !== 'string') {
            return '';
        }

        // RFID identifiers are strings, not numbers.
        // This preserves leading zeroes.
        return rawIdentifier
            .trim()
            .replace(/\s+/g, '')
            .toUpperCase();
    }
}