$prompt = Get-Content 'C:\Users\Administrator\Documents\GitHub\botmenfess\task_prompt.txt' -Raw
Write-Host "Creating task with prompt..."
kanban task create --prompt "$prompt"
