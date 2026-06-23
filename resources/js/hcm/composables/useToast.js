import { ref } from 'vue';

const message = ref('');
const type = ref('success');
const visible = ref(false);
let timer;

export function useToast() {
    const show = (msg, t = 'success') => {
        message.value = msg;
        type.value = t;
        visible.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => { visible.value = false; }, 3500);
    };

    return { message, type, visible, show };
}
