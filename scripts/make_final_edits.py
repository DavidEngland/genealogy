#!/usr/bin/env python3
# Author: David Edward England, PhD
# ORCID: https://orcid.org/0009-0001-2095-6646
# Repo: https://github.com/DavidEngland/genealogy
"""Make final remaining edits"""
import re

def make_final_edits():
    with open('The Shoals.md', 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    content = ''.join(lines)
    
    # 1. Chickasaws/Cherokees intro
    pattern1 = r'Chickasaws and Cherokees\n     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees\.     The Cherokees'
    replacement1 = 'Chickasaws and Cherokees\n     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees, though understanding their presence requires acknowledging the complex web of Indigenous relationships that predated colonial boundaries.\n     The Cherokees'
    
    if re.search(pattern1, content):
        content = re.sub(pattern1, replacement1, content)
        print("✓ Updated Chickasaws/Cherokees intro")
    
    # 2. Colbert family section header
    pattern2 = r'(Colbert negotiated the construction of a home, outbuildings and a ferryboat for his business\.)(\s+)(Arrell M\. Gibson)'
    replacement2 = r'\1\nThe Scottish Colbert Family and Indigenous Leadership\n     \3'
    
    if re.search(pattern2, content):
        content = re.sub(pattern2, replacement2, content)
        print("✓ Added Colbert family section header")
    
    # 3. Change "Scotsman" to "Scottish trader" and add context
    pattern3 = r'(The rise of the Colberts can be attributed to James Logan Colbert), who, according to Trace historian Dawsan A\. Phelps, was a Scotsman living among the Chickasaws as early as 1767\.'
    replacement3 = r'\1, a Scottish trader who, according to Trace historian Dawsan A. Phelps, was living among the Chickasaws as early as 1767.     James Logan Colbert'"'"'s integration into Chickasaw society through marriage to Chickasaw women created a family that would bridge two worlds, becoming powerful leaders within the Chickasaw nation while maintaining connections to Euro-American commerce and politics.'
    
    if re.search(pattern3, content):
        content = re.sub(pattern3, replacement3, content)
        print("✓ Updated Colbert heritage description")
    
    # 4. Update Colbert sons
    pattern4 = r'(Colbert had at least five sons who all rose to prominence in the tribe\.)(\s+)(The eldest)'
    replacement4 = r'\1, becoming influential leaders who navigated the complex political landscape between Indigenous sovereignty and American expansion.\n     \3'
    
    if re.search(pattern4, content):
        content = re.sub(pattern4, replacement4, content)
        print("✓ Updated Colbert sons description")
    
    # 5. Add ferry infrastructure context
    pattern5 = r'(He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819\.)(\s+)(Colbert became widely known)'
    replacement5 = r'\1     George Colbert'"'"'s ferry became essential infrastructure at the Shoals crossing, serving travelers on the Natchez Trace and facilitating the movement of people and goods through Chickasaw territory.\n     \3'
    
    if re.search(pattern5, content):
        content = re.sub(pattern5, replacement5, content)
        print("✓ Added ferry infrastructure context")
    
    # 6. Donelson expedition
    pattern6 = r'(These may have been the first American families to use McFarland Bottoms as a campground\.)(\s+)(After four months)'
    replacement6 = r'\1—though Indigenous peoples had used these crossing points and camp sites for thousands of years.     The Shoals and areas downriver as far as Waterloo had long served as major crossing points, and Waterloo would later become a tragic departure point during the Trail of Tears.\n     \3'
    
    if re.search(pattern6, content):
        content = re.sub(pattern6, replacement6, content)
        print("✓ Updated Donelson expedition context")
    
    with open('The Shoals.md', 'w', encoding='utf-8') as f:
        f.write(content)
    
    print("\n✓ All edits complete!")

if __name__ == '__main__':
    make_final_edits()
