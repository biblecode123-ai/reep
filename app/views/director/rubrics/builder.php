<!-- Rubric Builder Interface (Prototype) -->
<div class="rubric-builder">
    <h1>Create/Edit Evaluation Rubric</h1>
    
    <div id="criteria-list">
        <!-- Criteria rows will be dynamically added here -->
    </div>
    
    <div class="summary-panel">
        <p>Total Allocated Points: <span id="total-points">0</span> / 100</p>
        <div id="validation-message"></div>
    </div>
    
    <button id="publish-btn" disabled>Publish Rubric</button>
</div>

<script>
    // Logic to calculate points and toggle publish button
    function updateScore() {
        let total = 0;
        document.querySelectorAll('.criterion-points').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        
        document.getElementById('total-points').innerText = total;
        const publishBtn = document.getElementById('publish-btn');
        const validationMsg = document.getElementById('validation-message');
        
        if (total === 100) {
            publishBtn.disabled = false;
            validationMsg.innerText = "Rubric is valid.";
        } else {
            publishBtn.disabled = true;
            validationMsg.innerText = "Total points must be exactly 100. Current: " + total;
        }
    }
</script>
