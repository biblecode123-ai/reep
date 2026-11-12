(function () {
  'use strict';
  
  const searchInput = document.getElementById('researcherSearch');
  const resultsDiv = document.getElementById('results');
  const applicationId = window.applicationId;

  if (!searchInput) return;

  searchInput.addEventListener('input', function() {
    const q = this.value;
    if (q.length < 2) {
      resultsDiv.innerHTML = '';
      return;
    }

    resultsDiv.innerHTML = 'Searching...';

    window.mwuGet('index.php?action=api_researchers', { q: q })
      .then(function(res) {
        resultsDiv.innerHTML = '';
        if (!res.ok || res.data.length === 0) {
          resultsDiv.innerHTML = 'No researchers found.';
          return;
        }

        res.data.forEach(function(r) {
          const div = document.createElement('div');
          div.innerHTML = `
            <div style="border:1px solid #ddd; padding:5px; margin:5px 0;">
              ${r.full_name} (${r.college_name}) 
              <button class="btn btn-sm" onclick="inviteResearcher(${r.id})">Invite</button>
            </div>
          `;
          resultsDiv.appendChild(div);
        });
      });
  });

  window.inviteResearcher = function(userId) {
    window.mwuPost('index.php?action=application_add_member', { 
        application_id: applicationId, 
        user_id: userId 
    }).then(function(res) {
        if (res.ok) {
            window.location.reload();
        } else {
            alert(res.error);
        }
    });
  };

  window.respondInvitation = function(memberId, action) {
    window.mwuPost('index.php?action=application_respond_member', { 
        member_id: memberId, 
        action: action 
    }).then(function(res) {
        if (res.ok) {
            window.location.reload();
        } else {
            alert(res.error);
        }
    });
  };
})();

