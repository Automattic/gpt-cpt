document.addEventListener('DOMContentLoaded', function() {
    var knowledgeButton = document.getElementById('openKnowledge');
    var closeModal = document.getElementById('closeModalButton');

    knowledgeButton.addEventListener('click', function(event) {
        event.preventDefault();
        openModalWindow(knowledgeData.fileContents);
    });

    closeModal.addEventListener('click', function(event) {
        event.preventDefault();
        document.getElementById('knowledge').style.display = 'none';
    });

    function openModalWindow(data) {
        var knowledgeContent = document.getElementById('knowledgeContent');
        knowledgeContent.innerHTML = '<pre>' + data + '</pre>';
        document.getElementById('knowledge').style.display = 'block';
    }
});
