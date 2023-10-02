
    function uniquealp(str1,str2)
    {
        const set1 = new Set(str1)
        const set2 = new Set(str2)
        const uniquelet = new Set()

        for(const char of set1)
        {
            if(!set2.has(char))
            {
                uniquelet.add(char)
            }
        }

        for(const char of set2)
        {
            if(!set1.has(char))
            {
                uniquelet.add(char)
            }
        }
        return Array.from(uniquelet)
    }


let out = document.getElementById('out')
out.textContent = "ka"