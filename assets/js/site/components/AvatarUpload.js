export default function AvatarUpload(element) {
    element.querySelector('input[type="file"]').addEventListener('change', (event) => {
        if (event.target.files.length > 0) {
            element.submit();
        }
    });
}
