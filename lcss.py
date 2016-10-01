from collections import defaultdict
import webbrowser
import functools

s1 = """This is line 1

Line 2

Oxen
"""
s2 = """This is line 1


Line 2
Oxen
"""


def lcss_len(s1, s2):
    length = defaultdict(int)
    for i in range(0, len(s1)):
        for j in range(0, len(s2)):
            if s1[i] == s2[j]:
                length[i, j] = length[i - 1, j - 1] + 1
            else:
                length[i, j] = max(length[i, j - 1], length[i - 1, j])

    return length



def lcss(s1, s2):
    length = lcss_len(s1, s2)
    i, j = len(s1) - 1, len(s2) - 1
    items = []
    expected_length = length[i, j]
    while i >= 0 and j >= 0:
        if s1[i] == s2[j]:
            items.append(s1[i])
            i, j = i - 1, j - 1
        elif length[i, j] == length[i - 1, j]:
            i -= 1
        elif length[i, j] == length[i, j - 1]:
            j -= 1
        else:
            assert False
    assert len(items) == expected_length
    return list(reversed(items))

#print("lcss = ", lcss(s1, s2))


def makeDiffHtml(s1, s2):
    css = lcss(s1, s2)
    result = ''
    i = 0
    j = 0
    k = 0
    while i < len(s1) or j < len(s2):
        if i < len(s1) and (k >= len(css) or s1[i] != css[k]):
            result += "<del>"
            while i < len(s1) and (k >= len(css) or s1[i] != css[k]):
                result += s1[i]
                i += 1
            result += "</del>"
        if j < len(s2) and (k >= len(css) or s2[j] != css[k]):
            result += "<ins>"
            while j < len(s2) and (k >= len(css) or s2[j] != css[k]):
                result += s2[j]
                j += 1
            result += "</ins>"
        while i < len(s1) and j < len(s2) and k < len(css) and s1[i] == css[k] and s2[j] == css[k]:
            result += css[k]
            i, j, k = i + 1, j + 1, k + 1
    return result


def highlight_deleted(s, css, colour):
    result = ''
    i = 0
    j = 0
    deleting = False
    while i < len(s):
        if j >= len(css) or s[i] != css[j]:
            if not deleting:
                result += "<span style='background-color:{}; white-space:pre'>".format(colour)
                deleting = True
        else:
            if deleting:
                result += "</span>"
                deleting = False
            j += 1
        if deleting and '\n\n' in s[i]:
            first, last = s[i].split('\n\n')
            result += first + '</span>' + "<div style='background-color:green; display: block'><pre>\n</pre></div>";
            deleting = False
        else:
            result += s[i] #if s[i] != ' ' else '&nbsp;'
        i += 1
    if deleting:
        result += "</span>"
    return result


def tokens(s):
    """A list of tokens (diff elements) in s"""
    token_list = []
    if s:
        isws = s[0].isspace()
        tok = ''
        for c in s:
            if c.isspace() == isws:
                tok += c
            else:
                token_list.append(tok)
                tok = c
                isws = not isws
        token_list.append(tok)
    return token_list


s1 = """A line of text to check
A common line
And another line

A third line
"""
s2 = """My first line of text to check
A common line

Another line


A fourth line"""
#diff = makeDiffHtml(s1, s2)

s1 = tokens(s1)
print(s1)
s2 = tokens(s2)
css = lcss(s1, s2)
print(css)
html = "<table style='border:1px solid gray; border-collapse: collapse'><tr><td style='border:1px solid gray;vertical-align:top'><pre>"
html += highlight_deleted(s1, css, 'yellow')
html += "</pre></td><td style='border:1px solid gray;vertical-align:top'><pre>"
html += highlight_deleted(s2, css, 'red')
html += "</pre></td></tr></table>"
html = html.replace("\n", "<br>")
with open('junk.html', 'w') as fout:
    print(html, file=fout)
webbrowser.open('junk.html',new=2)


